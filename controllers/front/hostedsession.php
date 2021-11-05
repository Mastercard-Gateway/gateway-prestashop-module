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

require_once dirname(__FILE__) . '/abstract.php';

class MastercardHostedSessionModuleFrontController extends MastercardAbstractModuleFrontController
{
    /**
     * @inheritdoc
     * @throws \Http\Client\Exception
     */
    public function postProcess()
    {
        if (parent::postProcess()) {
            return;
        }

        try {
            $cart = Context::getContext()->cart;

            $customer = new Customer($cart->id_customer);
            if (!Validate::isLoadedObject($customer)) {
                $this->errors[] = $this->module->l('Invalid data (customer)', 'hostedsession');
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            }

            $this->_postProcess($cart, $customer);

            Tools::redirect(
                sprintf("index.php?controller=order-confirmation&id_cart=%d&id_module=%d&id_order=%s&key=%s",
                    (int)$cart->id, (int)$this->module->id, $this->module->currentOrder, $customer->secure_key)
            );

        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            $this->redirectWithNotifications(Context::getContext()->link->getPageLink('cart', null, null, array(
                'action' => 'show'
            )));
        }
    }

    /**
     * @param Cart $cart
     * @param Customer $customer
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    protected function _postProcess($cart, $customer)
    {
        $session = array(
            'id' => Tools::getValue('session_id')
        );

        $authentication = [
            'transactionId' => Tools::getValue('transaction_id')
        ];

        $treeDSVersion = Configuration::get('mpgs_hs_3ds');

        $orderData = $this->getOrderData();

        $billingAddress = new Address($cart->id_address_invoice);

        $shippingAddress = new Address($cart->id_address_delivery);

        // Create order before the payment occurs
        $this->module->validateOrder(
            (int)$cart->id,
            Configuration::get('MPGS_OS_PAYMENT_WAITING'),
            $cart->getOrderTotal(),
            MasterCard::PAYMENT_CODE,
            null,
            array(),
            Context::getContext()->currency->id,
            false,
            $customer->secure_key
        );

        $order = new Order((int)$this->module->currentOrder);

        $action = Configuration::get('mpgs_hs_payment_action');

        if ($action === Mastercard::PAYMENT_ACTION_AUTHCAPTURE) {
            $response = $this->client->authorize(
                $this->module->getNewOrderRef(),
                $orderData,
                $this->threeDSecureId,
                $session,
                $this->getContactForGateway($customer),
                $this->getAddressForGateway($billingAddress),
                $this->getAddressForGateway($shippingAddress),
                $this->getContactForGateway($shippingAddress),
                $authentication,
                $treeDSVersion
            );

            $processor = new ResponseProcessor($this->module);
            $processor->handle($order, $response, array(
                new RiskResponseHandler(),
                new AuthorizationResponseHandler(),
                new TransactionStatusResponseHandler(),
            ));

        } else if ($action === Mastercard::PAYMENT_ACTION_PAY) {
            $response = $this->client->pay(
                $this->module->getNewOrderRef(),
                $orderData,
                $this->threeDSecureId,
                $session,
                $this->getContactForGateway($customer),
                $this->getAddressForGateway($billingAddress),
                $this->getAddressForGateway($shippingAddress),
                $this->getContactForGateway($shippingAddress),
                $authentication,
                $treeDSVersion
            );

            $processor = new ResponseProcessor($this->module);
            $processor->handle($order, $response, array(
                new RiskResponseHandler(),
                new CaptureResponseHandler(),
                new ResponseStatusHandler(),
                new TransactionStatusResponseHandler(),
            ));

        } else {
            throw new Exception('Unexpected response from the Payment Gateway');
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getOrderData()
    {
        $deltaAmount = $this->getDeltaAmount();

        $currency = Context::getContext()->currency;
        return array(
            'currency' => $currency->iso_code,
            'amount' => GatewayService::numeric(
                Context::getContext()->cart->getOrderTotal()
            ),
            'item' => $this->module->getOrderItems($deltaAmount),
            'itemAmount' => $this->module->getItemAmount($deltaAmount),
            'shippingAndHandlingAmount' => $this->module->getShippingHandlingAmount($deltaAmount),
        );
    }
}
