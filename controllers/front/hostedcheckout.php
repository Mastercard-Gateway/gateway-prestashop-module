<?php
/**
 * Copyright (c) On Tap Networks Limited.
 */

class MastercardHostedCheckoutModuleFrontController extends ModuleFrontController
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
     * @throws PrestaShopException
     * @throws Exception
     */
    public function init()
    {
        parent::init();

        if (Context::getContext()->cart->id_customer == 0 ||
            Context::getContext()->cart->id_address_delivery == 0 ||
            Context::getContext()->cart->id_address_invoice == 0 ||
            !$this->module->active
        ) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer(Context::getContext()->cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $this->client = new GatewayService(
            $this->module->getApiEndpoint(),
            $this->module->getApiVersion(),
            $this->module->getConfigValue('mpgs_merchant_id'),
            $this->module->getConfigValue('mpgs_api_password'),
            $this->module->getWebhookUrl()
        );
    }

    /**
     * @throws GatewayResponseException
     * @throws \Http\Client\Exception
     */
    protected function createSessionAndRedirect()
    {
        $orderId = (int)Context::getContext()->cart->id;

        $response = $this->client->createCheckoutSession($orderId, Context::getContext()->currency->iso_code);

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
     */
    protected function showPaymentPage()
    {
        $this->context->smarty->assign(array(
            'mpgs_config' => array(
                'session_id' => Tools::getValue('session_id'),
                'session_version' => Tools::getValue('session_version'),
                'success_indicator' => Tools::getValue('success_indicator'),
                'checkout_component_url' => $this->module->getJsComponent(),
                'merchant_id' => $this->module->getConfigValue('mpgs_merchant_id'),
                'order_id' => (int)Context::getContext()->cart->id,
                'amount' => Context::getContext()->cart->getOrderTotal(),
                'currency' => Context::getContext()->currency->iso_code
            ),
        ));
        $this->setTemplate('module:mastercard/views/templates/front/methods/hostedcheckout/js.tpl');
    }

    /**
     * @throws \Http\Client\Exception
     * @throws PrestaShopException
     */
    protected function createOrderAndRedirect()
    {
        $orderId = Tools::getValue('order_id');
        $cart = Context::getContext()->cart;
        $currency = Context::getContext()->currency;

        if ((int)$orderId !== (int)$cart->id) {
            $this->errors[] = $this->module->l('Invalid data (order)');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->errors[] = $this->module->l('Invalid data (customer)');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        $response = $this->client->retrieveOrder($orderId);

        $this->module->validateOrder(
            (int)$cart->id,
            Configuration::get('MPGS_OS_PAYMENT_WAITING'),
            $response['amount'],
            $this->module->displayName,
            null,
            array(),
            $currency->id,
            false,
            $customer->secure_key
        );

        $order = new Order((int)$this->module->currentOrder);

        foreach ($response['transaction'] as $txn) {
            if ($txn['result'] != 'SUCCESS') {
                continue;
            }
            if ($txn['transaction']['type'] == 'AUTHORIZATION') {
                $order->addOrderPayment(
                    $txn['transaction']['amount'],
                    null,
                    'auth-' . $txn['transaction']['id']
                );
            } else if ($txn['transaction']['type'] == 'CAPTURE' || $txn['transaction']['type'] == 'PAYMENT') {
                $order->addOrderPayment(
                    $txn['transaction']['amount'],
                    null,
                    'capture-' . $txn['transaction']['id']
                );
            } else {
                throw new Exception('Unknown transaction status '.$txn['transaction']['type']);
            }
        }

        $newStatus = null;
        if ($response['status'] == "AUTHORIZED") {
            $newStatus = Configuration::get('MPGS_OS_AUTHORIZED');
        }

        if ($response['status'] == "CAPTURED") {
            $newStatus = Configuration::get('PS_OS_PAYMENT');
        }

        if ($newStatus) {
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($newStatus, $order, true);
            $history->addWithemail(true, array());
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
        if (Tools::getValue('cancel')) {
            $this->warning[] = $this->module->l('Payment was cancelled.');
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
