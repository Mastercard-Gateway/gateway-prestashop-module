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
                    ->setModule($this->module)
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
     * @var Module
     */
    protected $module;

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
     * @param Module $module
     * @return $this
     */
    public function setModule($module)
    {
        $this->module = $module;
        return $this;
    }

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

class CaptureResponseHandler extends TransactionResponseHandler
{
    /**
     * @inheritdoc
     */
    public function handle($order, $response)
    {
        parent::handle($order, $response);

        if (!$this->isApproved($response)) {
            throw new MasterCardPaymentException('Transaction not approved by the gateway');
        }

        $order->addOrderPayment(
            $response['transaction']['amount'],
            null,
            'capture-' . $response['transaction']['id']
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
        parent::handle($order, $response);

        if (!$this->isApproved($response)) {
            throw new MasterCardPaymentException('Transaction not approved by the gateway');
        }

        $order->addOrderPayment(
            $response['transaction']['amount'],
            null,
            'auth-' . $response['transaction']['id']
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
        if ($response['result'] != 'SUCCESS') {
            throw new MasterCardPaymentException($this->module->l('Your payment was declined.'));
        }
    }
}

class OrderStatusResponseHandler extends ResponseHandler
{
    /**
     * @param Order $order
     * @param array $response
     */
    public function handle($order, $response)
    {
        if ($order->getCurrentState() == Configuration::get('MPGS_OS_FRAUD')) {
            return;
        }

        if ($order->getCurrentState() == Configuration::get('MPGS_OS_REVIEW_REQUIRED')) {
            return;
        }

        $newStatus = null;

        if ($response['status'] == "AUTHORIZED") {
            $newStatus = Configuration::get('MPGS_OS_AUTHORIZED');
        }

        if ($response['status'] == "CAPTURED") {
            $newStatus = Configuration::get('PS_OS_PAYMENT');
        }

        // If can't figure out what status the order is in OR
        // If any previous handlers have failed, mark the order as failed
        if (!$newStatus || $this->hasExceptions()) {
            $newStatus = Configuration::get('PS_OS_ERROR');
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
        $orderStatusHandler->handle($order, $response['order']);
    }
}

class RiskResponseHandler extends ResponseHandler
{
    /**
     * @param Order $order
     * @param array $response
     */
    public function handle($order, $response)
    {
        $newStatus = null;

        if (isset($response['risk']['response'])) {
            $risk = $response['risk']['response'];

            if ($risk['gatewayCode'] == 'REVIEW_REQUIRED') {
                $newStatus = Configuration::get('MPGS_OS_REVIEW_REQUIRED');
            }
            if ($risk['gatewayCode'] == 'REJECTED') {
                $newStatus = Configuration::get('MPGS_OS_FRAUD');
            }
        }

        if ($newStatus) {
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
                $handler->handle($order, $txn);
            } else if ($txn['transaction']['type'] == 'CAPTURE' || $txn['transaction']['type'] == 'PAYMENT') {
                $handler = new CaptureResponseHandler();
                $handler->handle($order, $txn);
            } else {
                throw new MasterCardPaymentException('Unknown transaction status ' . $txn['transaction']['type']);
            }
        }
    }
}
