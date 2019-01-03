<?php
/**
 * Copyright (c) On Tap Networks Limited.
 */

require_once(dirname(__FILE__) . '/../../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../../gateway.php');

class AdminMpgsController extends ModuleAdminController
{
    /**
     * @var GatewayService
     */
    protected $client;

    /**
     * @var Mastercard
     */
    public $module;

    /**
     * @return bool|ObjectModel|void
     * @throws Exception
     */
    public function postProcess()
    {
        $action = Tools::getValue('action');
        $actionName = $action . 'Action';

        $this->client = new GatewayService(
            $this->module->getApiEndpoint(),
            $this->module->getApiVersion(),
            $this->module->getConfigValue('mpgs_merchant_id'),
            $this->module->getConfigValue('mpgs_api_password'),
            $this->module->getWebhookUrl()
        );

        $this->{$actionName}();

        parent::postProcess();
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \Http\Client\Exception
     */
    protected function voidAction()
    {
        $orderId = Tools::getValue('id_order');
        $order = new Order($orderId);

        try {
            $authTxnId = $this->module->findTxnId('auth', $order);

            if (!$authTxnId) {
                throw new Exception('Authorization transaction not found.');
            }

            $newTxnId = 'void-' . $authTxnId;
            $response = $this->client->voidTxn($order->id_cart, $newTxnId, $authTxnId);

            $amount = number_format(floatval($response['transaction']['amount']) * -1, 2, '.', '');
            $order->addOrderPayment(
                $amount,
                null,
                $response['transaction']['id']
            );

            $newStatus = Configuration::get('PS_OS_CANCELED');
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($newStatus, $order, true);
            $history->addWithemail(true, array());

        } catch (Exception $e) {
            $this->errors[] = $e->getMessage() . ' (' . $e->getCode() . ')';
            return false;
        }

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders').'&conf=4&id_order='.(int)$order->id.'&vieworder');
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Exception
     * @throws \Http\Client\Exception
     */
    protected function captureAction()
    {
        $orderId = Tools::getValue('id_order');
        $order = new Order($orderId);

        try {
            $authTxnId = $this->module->findTxnId('auth', $order);

            if (!$authTxnId) {
                throw new Exception('Authorization transaction not found.');
            }

            $authTxn = $this->module->findTxn('auth', $order);
            $currency = Currency::getCurrency($authTxn->id_currency);

            $newTxnId = 'capture-' . $authTxnId;
            $response = $this->client->captureTxn($order->id_cart, $newTxnId, $authTxn->amount, $currency['iso_code']);

            $order->addOrderPayment(
                $response['transaction']['amount'],
                null,
                $response['transaction']['id']
            );

            // @todo: Deletes auth transaction, otherwise the payment would appear to be doubled
            $authTxn->delete();

            $newStatus = Configuration::get('PS_OS_PAYMENT');
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($newStatus, $order, true);
            $history->addWithemail(true, array());

        } catch (Exception $e) {
            $this->errors[] = $e->getMessage() . ' (' . $e->getCode() . ')';
            return false;
        }

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders').'&conf=4&id_order='.(int)$order->id.'&vieworder');
    }

    /**
     * @return bool
     * @throws PrestaShopException
     * @throws \Http\Client\Exception
     */
    protected function refundAction()
    {
        $orderId = Tools::getValue('id_order');
        $order = new Order($orderId);

        try {
            $txnId = $this->module->findTxnId('capture', $order);
            $txn = $this->module->findTxn('capture', $order);

            if (!$txnId) {
                throw new Exception('Capture/Pay transaction not found.');
            }

            $currency = Currency::getCurrency($txn->id_currency);

            $newTxnId = 'refund-' . $txnId;
            $response = $this->client->refund($order->id_cart, $newTxnId, $txn->amount, $currency['iso_code']);

            $amount = number_format(floatval($response['transaction']['amount']) * -1, 2, '.', '');
            $order->addOrderPayment(
                $amount,
                null,
                $response['transaction']['id']
            );

            $newStatus = Configuration::get('PS_OS_REFUND');
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($newStatus, $order, true);
            $history->addWithemail(true, array());

        } catch (Exception $e) {
            $this->errors[] = $e->getMessage() . ' (' . $e->getCode() . ')';
            return false;
        }

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders').'&conf=4&id_order='.(int)$order->id.'&vieworder');
    }
}
