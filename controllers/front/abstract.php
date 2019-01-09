<?php
/**
 * Copyright (c) On Tap Networks Limited.
 */

abstract class MastercardAbstractModuleFrontController extends ModuleFrontController
{
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
            $threeDSecureId = Tools::getValue('3DSecureId');

            if (!$paRes || !$threeDSecureId) {
                $this->errors[] = $this->module->l('Payment error occurred (3D Secure).');
                $this->redirectWithNotifications(Context::getContext()->link->getPageLink('order', null, null, array(
                    'action' => 'show'
                )));
                exit;
            }

            $response = $this->client->process3dsResult($threeDSecureId, $paRes);

            if ($response['response']['gatewayRecommendation'] !== 'PROCEED') {
                $this->errors[] = $this->module->l('Your payment was declined by 3D Secure.');
                $this->redirectWithNotifications(Context::getContext()->link->getPageLink('order', null, null, array(
                    'action' => 'show'
                )));
                exit;
            }

            $this->threeDSecureData = array(
                'acsEci' => $response['3DSecure']['acsEci'],
                'authenticationToken' => $response['3DSecure']['authenticationToken'],
                'paResStatus' => $response['3DSecure']['paResStatus'],
                'veResEnrolled' => $response['3DSecure']['veResEnrolled'],
                'xid' => $response['3DSecure']['xid'],
            );

            return false;
        }

        if (Tools::getValue('check_3ds_enrollment') === "1") {
            $threeD = array(
                'authenticationRedirect' => array(
                    'pageGenerationMode' => 'CUSTOMIZED',
                    'responseUrl' => $this->context->link->getModuleLink(
                        $this->module->name,
                        Tools::getValue('controller'),
                        array(),
                        true
                    )
                )
            );

            $session = array(
                'id' => Tools::getValue('session_id')
            );

            $currency = Context::getContext()->currency;
            $order = array(
                'amount' => Context::getContext()->cart->getOrderTotal(),
                'currency' => $currency->iso_code,
            );

            $response = $this->client->check3dsEnrollment(
                $threeD,
                $order,
                $session
            );

            if ($response['response']['gatewayRecommendation'] !== 'PROCEED') {
                $this->errors[] = $this->module->l('Your payment was declined.');
                $this->redirectWithNotifications(Context::getContext()->link->getPageLink('order', null, null, array(
                    'action' => 'show'
                )));
                exit;
            }

            if (isset($response['3DSecure']['authenticationRedirect'])) {
                $tdsAuth = $response['3DSecure']['authenticationRedirect']['customized'];
                $this->context->smarty->assign(array(
                    'authenticationRedirect' => $tdsAuth,
                    'returnUrl' => $this->context->link->getModuleLink(
                        $this->module->name,
                        Tools::getValue('controller'),
                        array(
                            '3DSecureId' => $response['3DSecureId'],
                            'process_acs_result' => '1',
                            'session_id' => Tools::getValue('session_id'),
                            'session_version' => Tools::getValue('session_version'),
                        ),
                        true
                    )
                ));

                $this->setTemplate('module:mastercard/views/templates/front/threedsecure/form.tpl');
                return true;
            }
        }
        return false;
    }
}
