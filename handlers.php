<?php
/**
 * Copyright (c) 2019-2020 Mastercard
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
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
        $this->logger->pushHandler(new StreamHandler(
            _PS_ROOT_DIR_.'/var/logs/mastercard.log',
            Configuration::get('mpgs_logging_level')
        ));
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

    /**
     * @todo: This is currently almost identical to Order->addOrderPayment()
     *
     * @param Order $order
     * @param float $amount_paid
     * @param string $payment_transaction_id
     * @param array $txn
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function addOrderPayment($order, $amount_paid, $payment_transaction_id = null, $txn = array())
    {
        $order_payment = new OrderPayment();
        $order_payment->order_reference = $order->reference;
        $order_payment->id_currency = $order->id_currency;
        $order_payment->conversion_rate = 1;
        $order_payment->payment_method = $order->payment;
        $order_payment->transaction_id = $payment_transaction_id;
        $order_payment->amount = $amount_paid;
        $order_payment->date_add = null;

        if (isset($txn['sourceOfFunds'], $txn['sourceOfFunds']['provided'], $txn['sourceOfFunds']['provided']['card'])) {
            $order_payment->card_number = $txn['sourceOfFunds']['provided']['card']['number'];
            $order_payment->card_expiration = $txn['sourceOfFunds']['provided']['card']['expiry']['month'] . '/' . $txn['sourceOfFunds']['provided']['card']['expiry']['year'];
            $order_payment->card_brand = $txn['sourceOfFunds']['provided']['card']['brand'];
            $order_payment->card_holder = $txn['sourceOfFunds']['provided']['card']['nameOnCard'];
        }

        // Add time to the date if needed
        if ($order_payment->date_add != null && preg_match('/^[0-9]+-[0-9]+-[0-9]+$/', $order_payment->date_add)) {
            $order_payment->date_add .= ' '.date('H:i:s');
        }

        // Update total_paid_real value for backward compatibility reasons
        if ($order_payment->id_currency == $order->id_currency) {
            $order->total_paid_real += $order_payment->amount;
        } else {
            $order->total_paid_real += Tools::ps_round(Tools::convertPrice($order_payment->amount, $order_payment->id_currency, false), 2);
        }

        // We put autodate parameter of add method to true if date_add field is null
        $res = $order_payment->add(is_null($order_payment->date_add)) && $order->update();

        if (!$res) {
            return false;
        }

        return $res;
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

        $amount = number_format((float) $response['transaction']['amount'] * -1, 2, '.', '');
        $this->addOrderPayment(
            $order,
            $amount,
            $response['transaction']['id'],
            $response
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

        $this->addOrderPayment(
            $order,
            $response['transaction']['amount'],
            $response['transaction']['id'],
            $response
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

        $this->addOrderPayment(
            $order,
            $response['transaction']['amount'],
            $response['transaction']['id'],
            $response
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
