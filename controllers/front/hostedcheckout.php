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

require_once dirname(__FILE__) . '/abstract.php';

class MastercardHostedCheckoutModuleFrontController extends MastercardAbstractModuleFrontController
{
    /**
     * @throws GatewayResponseException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    protected function createSessionAndRedirect()
    {
        $orderId = $this->module->getNewOrderRef(true);

        $deltaCents = $this->getDeltaCents();

        $order = array(
            'id' => $orderId,
            'reference' => $orderId,
            'currency' => Context::getContext()->currency->iso_code,
            'amount' => GatewayService::numeric(
                Context::getContext()->cart->getOrderTotal()
            ),
            'item' => $this->module->getOrderItems($deltaCents),
            'itemAmount' => $this->module->getItemAmount($deltaCents),
            'shippingAndHandlingAmount' => $this->module->getShippingHandlingAmount($deltaCents),
        );

        $interaction = array(
            'theme' => GatewayService::safe(Configuration::get('mpgs_hc_theme')),
            'displayControl' => array(
                'shipping' => 'HIDE',
                'billingAddress' => GatewayService::safe(Configuration::get('mpgs_hc_show_billing')),
                'customerEmail' => GatewayService::safe(Configuration::get('mpgs_hc_show_email')),
                'orderSummary' => GatewayService::safe(Configuration::get('mpgs_hc_show_summary')),
            ),
            'merchant' => array(
                'name' => GatewayService::safe(Context::getContext()->shop->name, 40),
            ),
            'operation' => Configuration::get('mpgs_hc_payment_action')
        );

        /** @var ContextCore $context */
        $context = Context::getContext();

        /** @var CartCore $cart */
        $cart = $context->cart;

        /** @var AddressCore $billingAddress */
        $billingAddress = new Address($cart->id_address_invoice);

        /** @var AddressCore $shippingAddress */
        $shippingAddress = new Address($cart->id_address_delivery);

        /** @var CustomerCore $customer */
        $customer = Context::getContext()->customer;

        $response = $this->client->createCheckoutSession(
            $order,
            $interaction,
            $this->getContactForGateway($customer),
            $this->getAddressForGateway($billingAddress),
            $this->getAddressForGateway($shippingAddress),
            $this->getContactForGateway($shippingAddress)
        );

        $responseData = array(
            'session_id' => $response['session']['id'],
            'session_version' => $response['session']['version'],
            'success_indicator' => $response['successIndicator'],
        );

        if (ControllerCore::isXmlHttpRequest()) {
            header('Content-Type: application/json');
            exit(json_encode($responseData));
        }

        Tools::redirect(
            Context::getContext()->link->getModuleLink('mastercard', 'hostedcheckout', $responseData)
        );
    }

    /**
     * @throws PrestaShopException
     * @throws Exception
     */
    protected function showPaymentPage()
    {
        $this->context->smarty->assign(array(
            'mpgs_config' => array(
                'session_id' => Tools::getValue('session_id'),
                'session_version' => Tools::getValue('session_version'),
                'success_indicator' => Tools::getValue('success_indicator'),
                'merchant_id' => $this->module->getConfigValue('mpgs_merchant_id'),
                'order_id' => $this->module->getNewOrderRef(),
                'amount' => Context::getContext()->cart->getOrderTotal(),
                'currency' => Context::getContext()->currency->iso_code
            ),
            'hostedcheckout_component_url' => $this->module->getHostedCheckoutJsComponent(),
        ));
        $this->setTemplate('module:mastercard/views/templates/front/methods/hostedcheckout/js.tpl');
    }

    /**
     * @throws \Http\Client\Exception
     * @throws PrestaShopException
     * @throws Exception
     */
    protected function createOrderAndRedirect()
    {
        $orderIdParts = explode('-', Tools::getValue('order_id'));
        $orderIdOld = reset($orderIdParts);
        $cart = Context::getContext()->cart;
        $currency = Context::getContext()->currency;

        $orderId = $this->module->getNewOrderRef();
        $orderIdParts = explode('-', $orderId);

        if ($orderIdOld !== reset($orderIdParts)) {
            $this->errors[] = $this->module->l('Invalid data (order)', 'hostedcheckout');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->errors[] = $this->module->l('Invalid data (customer)', 'hostedcheckout');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        $response = $this->client->retrieveOrder($orderId);

        $this->module->validateOrder(
            (int)$cart->id,
            Configuration::get('MPGS_OS_PAYMENT_WAITING'),
            $response['amount'],
            MasterCard::PAYMENT_CODE,
            null,
            array(),
            $currency->id,
            false,
            $customer->secure_key
        );

        $order = new Order((int)$this->module->currentOrder);

        $processor = new ResponseProcessor($this->module);

        try {
            $processor->handle($order, $response, array(
                new RiskResponseHandler(),
                new OrderPaymentResponseHandler(),
                new OrderStatusResponseHandler(),
            ));
        } catch (Exception $e) {
            $this->errors[] = $this->module->l('Payment Error', 'hostedcheckout');
            $this->errors[] = $e->getMessage();
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        Tools::redirect(
            'index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key
        );
    }

    /**
     * @throws GatewayResponseException
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    public function postProcess()
    {
        if (parent::postProcess()) {
            return;
        }

        if (Tools::getValue('cancel')) {
            $this->warning[] = $this->module->l('Payment was cancelled.', 'hostedcheckout');
            $this->redirectWithNotifications(Context::getContext()->link->getPageLink('cart', null, null, array(
                'action' => 'show'
            )));
            exit;
        }

        if (!Tools::getValue('order_id')) {
            if (!Tools::getValue('success_indicator')) {
                $this->createSessionAndRedirect();
            } else {
                $this->showPaymentPage();
            }
        } else {
            $this->createOrderAndRedirect();
        }
    }
}
