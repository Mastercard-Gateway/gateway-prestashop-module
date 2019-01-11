<?php
/**
 * Copyright (c) On Tap Networks Limited.
 */

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class MasterCardPaymentException extends Exception
{

}

class ResponseProcessor
{
    /**
     * @var Module
     */
    public $module;

    /**
     * @var array
     */
    public $exceptions;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * ResponseProcessor constructor.
     * @param Module $module
     * @throws Exception
     */
    public function __construct($module)
    {
        $this->logger = new Logger('mastercard_handler');
        $this->logger->pushHandler(new StreamHandler(_PS_ROOT_DIR_.'/var/logs/mastercard.log'));
        $this->module = $module;
    }

    /**
     * @param Order $order
     * @param array $response
     * @param ResponseHandler[] $handlers
     * @throws MasterCardPaymentException
     */
    public function handle($order, $response, $handlers = array())
    {
        $this->exceptions = array();
        foreach ($handlers as $handler) {
            try {
                $handler
                    ->setProcessor($this)
                    ->handle($order, $response);
            }  catch (Exception $e) {
                $this->logger->critical('Payment Handler Exception', array('exception' => $e));
                $this->exceptions[] = $e->getMessage();
            }
        }

        if (!empty($this->exceptions)) {
            throw new MasterCardPaymentException(implode("\n", $this->exceptions));
        }
    }
}

abstract class ResponseHandler
{
    /**
     * @var ResponseProcessor
     */
    protected $processor;

    /**
     * @param Order $order
     * @param array $response
     * @throws MasterCardPaymentException
     */
    abstract public function handle($order, $response);


    /**
     * @param ResponseProcessor $processor
     * @return $this
     */
    public function setProcessor($processor)
    {
        $this->processor = $processor;
        return $this;
    }

    /**
     * @param $response
     * @return bool
     */
    protected function isApproved($response)
    {
        $gatewayCode = $response['response']['gatewayCode'];

        if (!in_array($gatewayCode, array('APPROVED', 'APPROVED_AUTO'))) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function hasExceptions()
    {
        return !empty($this->processor->exceptions);
    }
}

class RefundResponseHandler extends TransactionResponseHandler
{
    /**
     * @inheritdoc
     */
    public function handle($order, $response)
    {
        try {
            parent::handle($order, $response);
        } catch (MasterCardPaymentException $e) {
            $this->processor->logger->warning($e->getMessage());
            return;
        }

        $amount = number_format(floatval($response['transaction']['amount']) * -1, 2, '.', '');
        $order->addOrderPayment(
            $amount,
            null,
            $response['transaction']['id']
        );
    }
}

class VoidResponseHandler extends RefundResponseHandler
{

}

class CaptureResponseHandler extends TransactionResponseHandler
{
    /**
     * @inheritdoc
     */
    public function handle($order, $response)
    {
        try {
            parent::handle($order, $response);
        } catch (MasterCardPaymentException $e) {
            $this->processor->logger->warning($e->getMessage());
            return;
        }

        $order->addOrderPayment(
            $response['transaction']['amount'],
            null,
            $response['transaction']['id']
        );
    }
}


class AuthorizationResponseHandler extends TransactionResponseHandler
{
    /**
     * @inheritdoc
     */
    public function handle($order, $response)
    {
        try {
            parent::handle($order, $response);
        } catch (MasterCardPaymentException $e) {
            $this->processor->logger->warning($e->getMessage());
            return;
        }

        $order->addOrderPayment(
            $response['transaction']['amount'],
            null,
            $response['transaction']['id']
        );

    }
}

class TransactionResponseHandler extends ResponseHandler
{
    /**
     * @inheritdoc
     */
    public function handle($order, $response)
    {
        $state = $order->getCurrentOrderState();

        if ($state->id == Configuration::get('MPGS_OS_FRAUD')) {
            throw new MasterCardPaymentException(
                $this->processor->module->l('Payment is marked as fraud, action is blocked.')
            );
        }

        if ($state->id == Configuration::get('MPGS_OS_REVIEW_REQUIRED')) {
            throw new MasterCardPaymentException(
                $this->processor->module->l('Risk decision needed, action is blocked.')
            );
        }

        if (!$this->isApproved($response)) {
            throw new MasterCardPaymentException(
                $this->processor->module->l('The operation was declined.') . ' ('.$response['response']['gatewayCode'].')'
            );
        }
    }
}

class OrderStatusResponseHandler extends ResponseHandler
{
    /**
     * @param Order $order
     * @param array $response
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function handle($order, $response)
    {
        if ($order->getCurrentState() == Configuration::get('MPGS_OS_FRAUD')) {
            return;
        }

        if ($order->getCurrentState() == Configuration::get('MPGS_OS_REVIEW_REQUIRED')) {
            return;
        }

        if ($this->hasExceptions()) {
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $order, true);
            $history->addWithemail(true, array());
            return;
        }

        $newStatus = null;

        if ($response['status'] == "AUTHORIZED") {
            $newStatus = Configuration::get('MPGS_OS_AUTHORIZED');
        }

        if ($response['status'] == "CAPTURED") {
            $newStatus = Configuration::get('PS_OS_PAYMENT');
        }

        if ($response['status'] == 'VOID_AUTHORIZATION' || $response['status'] == 'CANCELLED') {
            $newStatus = Configuration::get('PS_OS_CANCELED');
        }

        if ($response['status'] == 'REFUNDED') {
            $newStatus = Configuration::get('PS_OS_REFUND');
        }

        if (!$newStatus) {
            $newStatus = Configuration::get('PS_OS_ERROR');
            $this->processor->logger->error(
                'Unexpected response status "' . $response['status'] . '"',
                array(
                    'response' => $response
                )
            );
        }

        $history = new OrderHistory();
        $history->id_order = (int)$order->id;
        $history->changeIdOrderState($newStatus, $order, true);
        $history->addWithemail(true, array());
    }
}

class TransactionStatusResponseHandler extends ResponseHandler
{
    /**
     * @inheritdoc
     */
    public function handle($order, $response)
    {
        $orderStatusHandler = new OrderStatusResponseHandler();
        $orderStatusHandler
            ->setProcessor($this->processor)
            ->handle($order, $response['order']);
    }
}

class RiskResponseHandler extends ResponseHandler
{
    /**
     * @param Order $order
     * @param array $response
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function handle($order, $response)
    {
        $newStatus = null;

        if (isset($response['risk']['response'])) {
            $risk = $response['risk']['response'];

            if ($risk['gatewayCode'] == 'REVIEW_REQUIRED') {
                if ($risk['review']['decision'] == 'PENDING') {
                    $newStatus = Configuration::get('MPGS_OS_REVIEW_REQUIRED');
                }
                if ($risk['review']['decision'] == 'ACCEPTED') {
                    $newStatus = Configuration::get('MPGS_OS_PAYMENT_WAITING');
                }
                if ($risk['review']['decision'] == 'REJECTED') {
                    $newStatus = Configuration::get('MPGS_OS_FRAUD');
                }
            }
            if ($risk['gatewayCode'] == 'REJECTED') {
                $newStatus = Configuration::get('MPGS_OS_FRAUD');
            }
        }

        $state = $order->getCurrentOrderState();

        if ($newStatus && $newStatus != $state->id) {
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($newStatus, $order, true);
            $history->addWithemail(true, array());
        }
    }
}

class OrderPaymentResponseHandler extends ResponseHandler
{
    /**
     * @inheritdoc
     */
    public function handle($order, $response)
    {
        foreach ($response['transaction'] as $txn) {
            if ($txn['transaction']['type'] == 'AUTHORIZATION') {
                $handler = new AuthorizationResponseHandler();
                $handler
                    ->setProcessor($this->processor)
                    ->handle($order, $txn);
            } else if ($txn['transaction']['type'] == 'CAPTURE' || $txn['transaction']['type'] == 'PAYMENT') {
                $handler = new CaptureResponseHandler();
                $handler
                    ->setProcessor($this->processor)
                    ->handle($order, $txn);
            } else {
                throw new MasterCardPaymentException('Unknown transaction type ' . $txn['transaction']['type']);
            }
        }
    }
}
