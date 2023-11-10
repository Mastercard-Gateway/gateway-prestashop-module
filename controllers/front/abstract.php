<?php
/**
 * Copyright (c) 2019-2023 Mastercard
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

abstract class MastercardAbstractModuleFrontController extends ModuleFrontController
{
    const PAYMENT_DECLINED_ERROR = 'Your payment was declined.';

    /**
     * @var GatewayService
     */
    public $client;

    /**
     * @var Mastercard
     */
    public $module;

    /**
     * @var array
     */
    public $threeDSecureData;

    /**
     * @var string
     */
    public $threeDSecureId;

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
     * If this method returns false, then execution is allowed on the child class level
     * otherwise child classes must return and not process the request
     *
     * @return bool
     * @throws PrestaShopException
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    public function postProcess()
    {
        parent::postProcess();

        if (Tools::getValue('process_acs_result') === "1") {
            $paRes = Tools::getValue('PaRes');
            $this->threeDSecureId = Tools::getValue('3DSecureId');

            if (!$paRes || !$this->threeDSecureId) {
                $this->errors[] = $this->module->l('Payment error occurred (3D Secure).', 'abstract');
                $this->redirectWithNotifications(
                    Context::getContext()->link->getPageLink(
                        'order',
                        true,
                        null,
                        array(
                            'action' => 'show',
                        )
                    )
                );
                exit;
            }

            $response = $this->client->process3dsResult($this->threeDSecureId, $paRes);

            if ($response['response']['gatewayRecommendation'] !== 'PROCEED') {
                $this->errors[] = $this->module->l('Your payment was declined by 3D Secure.', 'abstract');
                $this->redirectWithNotifications(
                    Context::getContext()->link->getPageLink(
                        'order',
                        true,
                        null,
                        array(
                            'action' => 'show',
                        ),
                        true
                    )
                );
                exit;
            }

            $this->threeDSecureData = array(
                'acsEci'              => $response['3DSecure']['acsEci'],
                'authenticationToken' => $response['3DSecure']['authenticationToken'],
                'paResStatus'         => $response['3DSecure']['paResStatus'],
                'veResEnrolled'       => $response['3DSecure']['veResEnrolled'],
                'xid'                 => $response['3DSecure']['xid'],
            );

            return false;
        }

        if (Tools::getValue('check_3ds_enrollment') === '2') {
            if (Tools::getValue('action_type') === 'update_session') {
                $currency = Context::getContext()->currency;
                $order = array(
                    'currency' => $currency->iso_code,
                    'amount'   => Context::getContext()->cart->getOrderTotal(),
                );

                $orderId = Tools::getValue('order_id');
                $sessionId = Tools::getValue('session_id');

                $responseUrl = Context::getContext()->link->getModuleLink(
                    'mastercard',
                    'threedsresponse',
                    array(
                        'session_id'           => $sessionId,
                        'action_type'          => 'completed',
                        'check_3ds_enrollment' => '2',
                    ),
                    true
                );

                $auth = array(
                    'channel'             => 'PAYER_BROWSER',
                    'redirectResponseUrl' => $responseUrl,
                );

                $transaction = array(
                    'id' => uniqid(sprintf('3DS-%s-', $orderId))
                );

                $response = $this->client->updateSession(
                    $orderId,
                    $sessionId,
                    $order,
                    $auth,
                    $transaction
                );

                $res = array(
                    'session'     => $response['session'] ?? [],
                    'order'       => $response['order'] ?? [],
                    'transaction' => $response['transaction'] ?? [],
                    'version'     => $response['version'] ?? [],
                );

                echo json_encode($res);
                exit;
            }
        }

        if (Tools::getValue('check_3ds_enrollment') === "1") {
            // reset order id
            $this->module->getNewOrderRef();
            $threeD = array(
                'authenticationRedirect' => array(
                    'pageGenerationMode' => 'CUSTOMIZED',
                    'responseUrl'        => $this->context->link->getModuleLink(
                        $this->module->name,
                        Tools::getValue('controller'),
                        array(),
                        true
                    ),
                ),
            );

            $session = array(
                'id' => Tools::getValue('session_id'),
            );

            $currency = Context::getContext()->currency;
            $order = array(
                'amount'   => Context::getContext()->cart->getOrderTotal(),
                'currency' => $currency->iso_code,
            );

            $response = $this->client->check3dsEnrollment(
                $threeD,
                $order,
                $session
            );

            if ($response['response']['gatewayRecommendation'] !== 'PROCEED') {
                $this->errors[] = $this->module->l(self::PAYMENT_DECLINED_ERROR, 'abstract');
                $this->redirectWithNotifications(
                    Context::getContext()->link->getPageLink(
                        'order',
                        true,
                        null,
                        array(
                            'action' => 'show',
                        )
                    )
                );
                exit;
            }

            if (isset($response['3DSecure']['authenticationRedirect'])) {
                $tdsAuth = $response['3DSecure']['authenticationRedirect']['customized'];
                $this->context->smarty->assign(
                    array(
                        'authenticationRedirect' => $tdsAuth,
                        'returnUrl'              => $this->context->link->getModuleLink(
                            $this->module->name,
                            Tools::getValue('controller'),
                            array(
                                '3DSecureId'         => $response['3DSecureId'],
                                'process_acs_result' => '1',
                                'session_id'         => Tools::getValue('session_id'),
                                'session_version'    => Tools::getValue('session_version'),
                            ),
                            true
                        ),
                    )
                );

                $this->setTemplate('module:mastercard/views/templates/front/threedsecure/form.tpl');

                return true;
            }
        }

        return false;
    }

    /**
     * @param AddressCore $address
     *
     * @return array
     */
    public function getAddressForGateway($address)
    {
        /** @var CountryCore $country */
        $country = new Country($address->id_country);

        return array(
            'city'        => GatewayService::safe($address->city, 100),
            'country'     => $this->module->iso2ToIso3($country->iso_code),
            'postcodeZip' => GatewayService::safe($address->postcode, 10),
            'street'      => GatewayService::safe($address->address1, 100),
            'street2'     => GatewayService::safe($address->address2, 100),
            'company'     => GatewayService::safe($address->company, 100),
        );
    }

    /**
     * @param CustomerCore|AddressCore $customer
     *
     * @return array
     */
    public function getContactForGateway($customer)
    {
        return array(
            'firstName'   => GatewayService::safe($customer->firstname, 50),
            'lastName'    => GatewayService::safe($customer->lastname, 50),
            'email'       => GatewayService::safeProperty($customer, 'email'),
            'mobilePhone' => GatewayService::safeProperty($customer, 'phone_mobile'),
            'phone'       => GatewayService::safeProperty($customer, 'phone'),
        );
    }

    /**
     * @return float
     */
    protected function getDeltaAmount()
    {
        if (!Configuration::get('mpgs_lineitems_enabled')) {
            return 0.00;
        }

        $total = Context::getContext()->cart->getOrderTotal();

        $precision = $this->getCurrencyPrecision();
        $cents = pow(10, $precision);
        $delta = round(($this->module->getItemAmount() * $cents) - ($total * $cents));
        $deltaAmount = $delta / $cents;

        return max($deltaAmount, 0.00);
    }

    /**
     * Retrieves the value of the Current Currency Decimals (precision on the data level)
     *
     * @return int
     */
    protected function getCurrencyPrecision()
    {
        $defaultValue = 2;
        $currency = Context::getContext()->currency;
        if (!$currency) {
            return $defaultValue;
        }

        $precision = $currency->precision;
        if (!$precision || $precision <= 0) {
            return $defaultValue;
        }

        return (int)$precision;
    }
}
