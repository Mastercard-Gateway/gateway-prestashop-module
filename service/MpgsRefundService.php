<?php
/**
 * Copyright (c) 2019-2021 Mastercard
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

require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../gateway.php');
require_once(dirname(__FILE__) . '/../handlers.php');

class MpgsRefundService
{
    /**
     * @var GatewayService
     */
    private $client;

    /**
     * @var Mastercard
     */
    private $module;

    /**
     * MpgsRefundService constructor.
     * @param Mastercard $module
     */
    public function __construct(
        Mastercard $module
    ) {
        $this->client = new GatewayService(
            $module->getApiEndpoint(),
            $module->getApiVersion(),
            $module->getConfigValue('mpgs_merchant_id'),
            $module->getConfigValue('mpgs_api_password'),
            $module->getWebhookUrl()
        );
        $this->module = $module;
    }

    /**
     * @param Order $order
     * @param ResponseHandler[] $handlers
     * @param int $amount
     * @param string $tnxNumber
     * @throws MasterCardPaymentException
     * @throws \Http\Client\Exception
     */
    public function execute($order, array $handlers = [], $amount = 0, $tnxNumber = '')
    {
        $txnData = $this->client->getCaptureTransaction($this->module->getOrderRef($order));
        $txn = $this->module->getTransactionById($order, $txnData['transaction']['id']);

        if (!$txn) {
            throw new Exception('Capture/Pay transaction not found.');
        }

        $currency = Currency::getCurrency($txn->id_currency);

        $response = $this->client->refund(
            $this->module->getOrderRef($order),
            $tnxNumber ? $tnxNumber : $txn->transaction_id,
            $amount ? $amount : $txn->amount,
            $currency['iso_code']
        );

        $processor = new ResponseProcessor($this->module);
        $processor->handle($order, $response, $handlers);

        return $response;
    }
}
