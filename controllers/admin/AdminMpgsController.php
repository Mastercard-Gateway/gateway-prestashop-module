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

require_once(dirname(__FILE__) . '/../../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../../gateway.php');
require_once(dirname(__FILE__) . '/../../handlers.php');
require_once(dirname(__FILE__) . '/../../service/MpgsRefundService.php');

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

        $orderId = Tools::getValue('id_order');
        $order = new Order($orderId);

        try {
            $this->{$actionName}($order);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders').'&conf=4&id_order='.(int)$order->id.'&vieworder');
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage() . ' (' . $e->getCode() . ')';
        }

        parent::postProcess();
    }

    /**
     * @param Order $order
     */
    protected function acceptAction($order)
    {
        // @todo
    }

    /**
     * @param Order $order
     */
    protected function rejectAction($order)
    {
        // @todo
    }

    /**
     * @param Order $order
     * @throws MasterCardPaymentException
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    protected function voidAction($order)
    {
        $txnData = $this->client->getAuthorizationTransaction($this->module->getOrderRef($order));
        $txn = $this->module->getTransactionById($order, $txnData['transaction']['id']);

        if (!$txn) {
            throw new Exception('Authorization transaction not found.');
        }

        $response = $this->client->voidTxn($this->module->getOrderRef($order), $txn->transaction_id);

        $processor = new ResponseProcessor($this->module);
        $processor->handle($order, $response, array(
            new VoidResponseHandler(),
            new TransactionStatusResponseHandler(),
        ));
    }

    /**
     * @param Order $order
     * @throws MasterCardPaymentException
     * @throws PrestaShopException
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    protected function captureAction($order)
    {
        $txnData = $this->client->getAuthorizationTransaction($this->module->getOrderRef($order));
        $txn = $this->module->getTransactionById($order, $txnData['transaction']['id']);

        if (!$txn) {
            throw new Exception('Authorization transaction not found.');
        }

        $currency = Currency::getCurrency($txn->id_currency);

        $response = $this->client->captureTxn(
            $this->module->getOrderRef($order),
            $txn->transaction_id,
            $txn->amount,
            $currency['iso_code']
        );

        $processor = new ResponseProcessor($this->module);
        $processor->handle($order, $response, array(
            new CaptureResponseHandler(),
            new TransactionStatusResponseHandler(),
        ));

        $txn->delete();
    }

    /**
     * @param Order $order
     * @throws MasterCardPaymentException
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    protected function refundAction($order)
    {
        $refundService = new MpgsRefundService($this->module);

        $refundService->execute($order, array(
            new TransactionResponseHandler(),
            new TransactionStatusResponseHandler(),
        ));
    }
}
