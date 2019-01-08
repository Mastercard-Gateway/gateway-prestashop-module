<?php
/**
 * Copyright (c) On Tap Networks Limited.
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
                $this->errors[] = $this->module->l('Invalid data (customer)');
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            }

            $this->_postProcess($cart, $customer);

            Tools::redirect(
                'index.php?controller=order-confirmation&id_cart=' . (int)$cart->id . '&id_module=' . (int)$this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key
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
        $currency = Context::getContext()->currency;
        $session = array(
            'id' => Tools::getValue('session_id'),
            'version' => Tools::getValue('session_version'),
        );

        $address = new Address(Context::getContext()->cart->id_address_invoice);
        $country = new Country($address->id_country);
        $billing = array(
            'address' => array(
                'city' => GatewayService::safe($address->city, 100),
                'country' => $this->module->iso2ToIso3($country->iso_code),
                'postcodeZip' => GatewayService::safe($address->postcode, 10),
                'street' => GatewayService::safe($address->address1, 100),
                'street2' => GatewayService::safe($address->address2, 100),
            )
        );

        $customerData = array(
            'email' => GatewayService::safe($customer->email),
            'firstName' => GatewayService::safe($customer->firstname, 50),
            'lastName' => GatewayService::safe($customer->lastname, 50),
        );

        // Create order before the payment occurs
        $this->module->validateOrder(
            (int)$cart->id,
            Configuration::get('MPGS_OS_PAYMENT_WAITING'),
            $cart->getOrderTotal(),
            $this->module->displayName,
            null,
            array(),
            $currency->id,
            false,
            $customer->secure_key
        );

        $order = new Order((int)$this->module->currentOrder);

        $action = Configuration::get('mpgs_hs_payment_action');

        if ($action === Mastercard::PAYMENT_ACTION_AUTHCAPTURE) {
            $response = $this->client->authorize(
                $cart->id,
                GatewayService::numeric(
                    $cart->getOrderTotal()
                ),
                $currency->iso_code,
                $this->threeDSecureData ? : null,
                $session,
                $customerData,
                $billing
            );

            if ($this->client->isApproved($response)) {
                $order->addOrderPayment(
                    $response['transaction']['amount'],
                    null,
                    'auth-' . $response['transaction']['id']
                );

                $newStatus = Configuration::get('MPGS_OS_AUTHORIZED');
            } else {
                $newStatus = Configuration::get('PS_OS_ERROR');
            }
        } else if ($action === Mastercard::PAYMENT_ACTION_PAY) {
            $response = $this->client->pay(
                $cart->id,
                GatewayService::numeric(
                    $cart->getOrderTotal()
                ),
                $currency->iso_code,
                $this->threeDSecureData ? : null,
                $session,
                $customerData,
                $billing
            );

            if ($this->client->isApproved($response)) {
                $order->addOrderPayment(
                    $response['transaction']['amount'],
                    null,
                    'capture-' . $response['transaction']['id']
                );

                $newStatus = Configuration::get('PS_OS_PAYMENT');
            } else {
                $newStatus = Configuration::get('PS_OS_ERROR');
            }
        } else {
            throw new Exception('Unexpected response from the Payment Gateway');
        }

        if ($newStatus) {
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($newStatus, $order, true);
            $history->addWithemail(true, array());
        }

        if (!isset($response['result']) || $response['result'] !== "SUCCESS") {
            throw new Exception($this->module->l('Your payment was declined.'));
        }
    }
}
