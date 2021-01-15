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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

define('MPGS_ISO3_COUNTRIES', include dirname(__FILE__).'/iso3.php');

require_once(dirname(__FILE__) . '/vendor/autoload.php');
require_once(dirname(__FILE__) . '/gateway.php');
require_once(dirname(__FILE__) . '/handlers.php');
require_once(dirname(__FILE__) . '/service/MpgsRefundService.php');
require_once(dirname(__FILE__) . '/model/MpgsRefund.php');
require_once(dirname(__FILE__) . '/model/MpgsOrderSuffix.php');

/**
 * @property bool bootstrap
 */
class Mastercard extends PaymentModule
{
    const PAYMENT_CODE = 'MPGS';
    const MPGS_API_VERSION = '58';

    const PAYMENT_ACTION_PAY = 'PAY';
    const PAYMENT_ACTION_AUTHCAPTURE = 'AUTHCAPTURE';
    const PAYMENT_CHECKOUT_SESSION_PURCHASE = 'PURCHASE';
    const PAYMENT_CHECKOUT_SESSION_AUTHORIZE = 'AUTHORIZE';

    /**
     * @var string
     */
    protected $_html = '';

    /**
     * @var string
     */
    protected $controllerAdmin;

    /**
     * @var array
     */
    protected $_postErrors = array();

    /**
     * Mastercard constructor.
     */
    public function __construct()
    {
        $this->module_key = '5e026a47ceedc301311e969c872f8d41';

        $this->name = 'mastercard';
        $this->tab = 'payments_gateways';

        $this->version = '1.3.5';
        if (!defined('MPGS_VERSION')) {
            define('MPGS_VERSION', $this->version);
        }

        $this->author = 'OnTap Networks Limited';
        $this->need_instance = 1;
        $this->controllers = array('payment', 'validation');
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;
        parent::__construct();

        $this->controllerAdmin = 'AdminMpgs';
        $this->displayName = $this->l('Mastercard Payment Gateway Services');
        $this->description = $this->l('Mastercard Payment Gateway Services module for Prestashop');

//        $this->limited_countries = array('FR');
//        $this->limited_currencies = array('EUR');
//        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
//        $helper->token = Tools::getAdminTokenLite('AdminModules');
    }

    /**
     * @param string $iso2country
     * @return string
     */
    public function iso2ToIso3($iso2country)
    {
        return MPGS_ISO3_COUNTRIES[$iso2country];
    }

    /**
     * @return string
     */
    public static function getApiVersion()
    {
        return self::MPGS_API_VERSION;
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     * @throws Exception
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        if (!$this->installOrderState()) {
            return false;
        }

        // Install admin tab
        if (!$this->installTab()) {
            return false;
        }

        return parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayAdminOrderLeft') &&
            $this->registerHook('displayAdminOrderSideBottom') &&
            $this->registerHook('displayBackOfficeOrderActions') &&
            $this->registerHook('actionObjectOrderSlipAddAfter');
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        Configuration::deleteByName('mpgs_hc_title');
        Configuration::deleteByName('mpgs_hs_title');

//        Configuration::deleteByName('MPGS_OS_PAYMENT_WAITING');
//        Configuration::deleteByName('MPGS_OS_AUTHORIZED');

        $this->unregisterHook('paymentOptions');
        $this->unregisterHook('displayBackOfficeOrderActions');
        $this->unregisterHook('displayAdminOrderLeft');
        $this->unregisterHook('displayAdminOrderSideBottom');
        $this->unregisterHook('actionObjectOrderSlipAddAfter');

        $this->uninstallTab();

        return parent::uninstall();
    }

    /**
     * @param $params
     */
    public function hookDisplayBackOfficeOrderActions($params)
    {
        // noop
    }

    /**
     * @return int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function installTab()
    {
        $tab = new Tab();
        $tab->class_name = $this->controllerAdmin;
        $tab->active = 1;
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $this->name;
        }
        $tab->id_parent = -1;
        $tab->module = $this->name;

        return $tab->add();
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName($this->controllerAdmin);
        $tab = new Tab($id_tab);
        if (Validate::isLoadedObject($tab)) {
            return $tab->delete();
        }
        return true;
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function installOrderState()
    {
        if (!Configuration::get('MPGS_OS_PAYMENT_WAITING')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('MPGS_OS_PAYMENT_WAITING')))) {

            $order_state = new OrderState();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Awaiting Payment';
            }
            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->paid = false;
            if ($order_state->add()) {
                $source = _PS_ROOT_DIR_.'/img/os/10.gif';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $order_state->id.'.gif';
                copy($source, $destination);
            }

            Configuration::updateValue('MPGS_OS_PAYMENT_WAITING', (int) $order_state->id);
        }
        if (!Configuration::get('MPGS_OS_AUTHORIZED')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('MPGS_OS_AUTHORIZED')))) {

            $order_state = new OrderState();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Payment Authorized';
                $order_state->template[$language['id_lang']] = 'payment';
            }
            $order_state->send_email = true;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = true;
            $order_state->paid = true;
            $order_state->invoice = false;
            if ($order_state->add()) {
                $source = _PS_ROOT_DIR_.'/img/os/10.gif';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $order_state->id.'.gif';
                copy($source, $destination);
            }

            Configuration::updateValue('MPGS_OS_AUTHORIZED', (int) $order_state->id);
        }
        if (!Configuration::get('MPGS_OS_REVIEW_REQUIRED')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('MPGS_OS_REVIEW_REQUIRED')))) {

            $order_state = new OrderState();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Payment Review Required';
            }
            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->paid = false;
            if ($order_state->add()) {
                $source = _PS_ROOT_DIR_.'/img/os/10.gif';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $order_state->id.'.gif';
                copy($source, $destination);
            }

            Configuration::updateValue('MPGS_OS_REVIEW_REQUIRED', (int) $order_state->id);
        }
        if (!Configuration::get('MPGS_OS_FRAUD')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('MPGS_OS_FRAUD')))) {

            $order_state = new OrderState();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Suspected Fraud';
            }
            $order_state->send_email = false;
            $order_state->color = '#DC143C';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->paid = false;
            if ($order_state->add()) {
                $source = _PS_ROOT_DIR_.'/img/os/6.gif';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $order_state->id.'.gif';
                copy($source, $destination);
            }
            Configuration::updateValue('MPGS_OS_FRAUD', (int) $order_state->id);
        }
        return true;
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitMastercardModule')) == true) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->context->controller->addJS($this->_path.'/views/js/back.js');
        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'mpgs_gateway_validated' => Configuration::get('mpgs_gateway_validated')
        ]);
        $this->_html .= $this->display($this->local_path, 'views/templates/admin/configure.tpl');
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    /**
     * @return void
     */
    protected function _postValidation()
    {
        if (!Tools::getValue('mpgs_api_url')) {
            if (!Tools::getValue('mpgs_api_url_custom')) {
                $this->_postErrors[] = $this->l('Custom API Endpoint is required.');
            }
        }
        if (Tools::getValue('mpgs_mode') === "1") {
            if (!Tools::getValue('mpgs_merchant_id')) {
                $this->_postErrors[] = $this->l('Merchant ID is required.');
            }
//            if (!Tools::getValue('mpgs_api_password')) {
//                $this->_postErrors[] = $this->l('API password is required.');
//            }
//            if (!Tools::getValue('mpgs_webhook_secret')) {
//                $this->_postErrors[] = $this->l('Webhook Secret is required.');
//            }
        } else {
            if (!Tools::getValue('test_mpgs_merchant_id')) {
                $this->_postErrors[] = $this->l('Test Merchant ID is required.');
            }
//            if (!Tools::getValue('test_mpgs_api_password')) {
//                $this->_postErrors[] = $this->l('Test API password is required.');
//            }
            // In test mode, the Secret is not required
//            if (!Tools::getValue('test_mpgs_webhook_secret')) {
//                $this->_postErrors[] = $this->l('Test Webhook Secret is required.');
//            }
        }
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     * @throws PrestaShopException
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMastercardModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getAdminFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array(
            $this->getAdminGeneralSettingsForm(),
            $this->getAdminHostedCheckoutForm(),
            $this->getAdminHostedSessionForm(),
            $this->getAdminAdvancedSettingForm(),
        ));
    }

    /**
     * @return array
     */
    protected function getApiUrls()
    {
        return array(
            'eu-gateway.mastercard.com' => $this->l('eu-gateway.mastercard.com'),
            'ap-gateway.mastercard.com' => $this->l('ap-gateway.mastercard.com'),
            'na-gateway.mastercard.com' => $this->l('na-gateway.mastercard.com'),
            'mtf.gateway.mastercard.com' => $this->l('mtf.gateway.mastercard.com'),
            '' => $this->l('Other'),
        );
    }

    /**
     * @return array
     */
    protected function getAdminFormValues()
    {
        $hcTitle = array();
        $hsTitle = array();
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $value = Tools::getValue(
                'mpgs_hc_title_' . $lang['id_lang'],
                Configuration::get('mpgs_hc_title', $lang['id_lang'])
            );
            $hcTitle[$lang['id_lang']] = $value ? $value : $this->l('MasterCard Hosted Checkout');

            $value = Tools::getValue(
                'mpgs_hs_title_' . $lang['id_lang'],
                Configuration::get('mpgs_hs_title', $lang['id_lang'])
            );
            $hsTitle[$lang['id_lang']] = $value ? $value : $this->l('MasterCard Hosted Session');
        }

        return array(
            'mpgs_hc_active' => Tools::getValue('mpgs_hc_active', Configuration::get('mpgs_hc_active')),
            'mpgs_hc_title' => $hcTitle,
            'mpgs_hc_payment_action' => Tools::getValue('mpgs_hc_payment_action', Configuration::get('mpgs_hc_payment_action')),
            'mpgs_hc_theme' => Tools::getValue('mpgs_hc_theme', Configuration::get('mpgs_hc_theme')),
            'mpgs_hc_show_billing' => Tools::getValue('mpgs_hc_show_billing', Configuration::get('mpgs_hc_show_billing') ? : 'HIDE'),
            'mpgs_hc_show_email' => Tools::getValue('mpgs_hc_show_email', Configuration::get('mpgs_hc_show_email') ? : 'HIDE'),
            'mpgs_hc_show_summary' => Tools::getValue('mpgs_hc_show_summary', Configuration::get('mpgs_hc_show_summary') ? : 'HIDE'),

            'mpgs_hs_active' => Tools::getValue('mpgs_hs_active', Configuration::get('mpgs_hs_active')),
            'mpgs_hs_title' => $hsTitle,
            'mpgs_hs_payment_action' => Tools::getValue('mpgs_hs_payment_action', Configuration::get('mpgs_hs_payment_action')),
            'mpgs_hs_3ds' => Tools::getValue('mpgs_hs_3ds', Configuration::get('mpgs_hs_3ds')),

            'mpgs_mode' => Tools::getValue('mpgs_mode', Configuration::get('mpgs_mode')),
            'mpgs_order_prefix' => Tools::getValue('mpgs_order_prefix', Configuration::get('mpgs_order_prefix')),
            'mpgs_api_url' => Tools::getValue('mpgs_api_url', Configuration::get('mpgs_api_url')),
            'mpgs_api_url_custom' => Tools::getValue('mpgs_api_url_custom', Configuration::get('mpgs_api_url_custom')),
            'mpgs_lineitems_enabled' => Tools::getValue('mpgs_lineitems_enabled', Configuration::get('mpgs_lineitems_enabled') ? : "1"),
            'mpgs_webhook_url' => Tools::getValue('mpgs_webhook_url', Configuration::get('mpgs_webhook_url')),
            'mpgs_logging_level' => Tools::getValue('mpgs_logging_level', Configuration::get('mpgs_logging_level') ? : \Monolog\Logger::ERROR),

            'mpgs_merchant_id' => Tools::getValue('mpgs_merchant_id', Configuration::get('mpgs_merchant_id')),
            'mpgs_api_password' => Tools::getValue('mpgs_api_password', Configuration::get('mpgs_api_password')),
            'mpgs_webhook_secret' => Tools::getValue('mpgs_webhook_secret', Configuration::get('mpgs_webhook_secret') ? : null),

            'test_mpgs_merchant_id' => Tools::getValue('test_mpgs_merchant_id', Configuration::get('test_mpgs_merchant_id')),
            'test_mpgs_api_password' => Tools::getValue('test_mpgs_api_password', Configuration::get('test_mpgs_api_password')),
            'test_mpgs_webhook_secret' => Tools::getValue('test_mpgs_webhook_secret', Configuration::get('test_mpgs_webhook_secret') ? : null),
        );
    }

    /**
     * @return array
     */
    protected function getAdminHostedCheckoutForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Payment Method Settings - Hosted Checkout'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enabled'),
                        'name' => 'mpgs_hc_active',
                        'is_bool' => true,
                        'desc' => '',
                        'values' => array(
                            array(
                                'id' => 'active_off',
                                'value' => true,
                                'label' => $this->l('Disabled'),
                            ),
                            array(
                                'id' => 'active_on',
                                'value' => false,
                                'label' => $this->l('Enabled'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Title'),
                        'name' => 'mpgs_hc_title',
                        'required' => true,
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Theme'),
                        'name' => 'mpgs_hc_theme',
                        'required' => false
                    ),
//                    array(
//                        'type' => 'select',
//                        'label' => $this->l('Billing Address display'),
//                        'name' => 'mpgs_hc_show_billing',
//                        'options' => array(
//                            'query' => array(
//                                array('id' => 'HIDE', 'name' => $this->l('Hide')),
//                                array('id' => 'MANDATORY', 'name' => $this->l('Mandatory')),
//                                array('id' => 'OPTIONAL', 'name' => $this->l('Optional')),
//                            ),
//                            'id' => 'id',
//                            'name' => 'name',
//                        ),
//                    ),
//                    array(
//                        'type' => 'select',
//                        'label' => $this->l('Email Address display'),
//                        'name' => 'mpgs_hc_show_email',
//                        'options' => array(
//                            'query' => array(
//                                array('id' => 'HIDE', 'name' => $this->l('Hide')),
//                                array('id' => 'MANDATORY', 'name' => $this->l('Mandatory')),
//                                array('id' => 'OPTIONAL', 'name' => $this->l('Optional')),
//                            ),
//                            'id' => 'id',
//                            'name' => 'name',
//                        ),
//                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Payment Model'),
                        'name' => 'mpgs_hc_payment_action',
                        'options' => array(
                            'query' => array(
                                array('id' => self::PAYMENT_CHECKOUT_SESSION_PURCHASE, 'name' => $this->l('Purchase (Pay)')),
                                array('id' => self::PAYMENT_CHECKOUT_SESSION_AUTHORIZE, 'name' => $this->l('Authorize & Capture')),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Order Summary display'),
                        'name' => 'mpgs_hc_show_summary',
                        'options' => array(
                            'query' => array(
                                array('id' => 'HIDE', 'name' => $this->l('Hide')),
                                array('id' => 'SHOW', 'name' => $this->l('Show')),
                                array('id' => 'SHOW_PARTIAL', 'name' => $this->l('Show (without payment details)')),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            )
        );
    }

    /**
     * @return array
     */
    protected function getAdminHostedSessionForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Payment Method Settings - Hosted Session'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enabled'),
                        'name' => 'mpgs_hs_active',
                        'is_bool' => true,
                        'desc' => '',
                        'values' => array(
                            array(
                                'id' => 'active_off',
                                'value' => true,
                                'label' => $this->l('Disabled'),
                            ),
                            array(
                                'id' => 'active_on',
                                'value' => false,
                                'label' => $this->l('Enabled'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Title'),
                        'name' => 'mpgs_hs_title',
                        'required' => true,
                        'lang' => true,
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Payment Model'),
                        'name' => 'mpgs_hs_payment_action',
                        'options' => array(
                            'query' => array(
                                array('id' => self::PAYMENT_ACTION_PAY, 'name' => $this->l('Purchase (Pay)')),
                                array('id' => self::PAYMENT_ACTION_AUTHCAPTURE, 'name' => $this->l('Authorize & Capture')),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('3D Secure'),
                        'name' => 'mpgs_hs_3ds',
                        'options' => array(
                            'query' => array(
                                array('value' => '', 'name' => $this->l('Disabled')),
                                array('value' => '1', 'name' => $this->l('3DS')),
                                array('value' => '2', 'name' => $this->l('EMV 3DS (3DS2)')),
                            ),
                            'id' => 'value',
                            'name' => 'name',
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            )
        );
    }

    /**
     * @return array
     */
    protected function getAdminGeneralSettingsForm()
    {
        $apiOptions = array();
        $c = 0;
        foreach ($this->getApiUrls() as $url => $label) {
            $apiOptions[] = array(
                'id' => 'api_' . $c,
                'value' => $url,
                'label' => $label,
            );
            $c++;
        }

        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('General Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live Mode'),
                        'name' => 'mpgs_mode',
                        'is_bool' => true,
                        'desc' => '',
                        'values' => array(
                            array(
                                'id' => 'active_off',
                                'value' => true,
                                'label' => $this->l('Disabled'),
                            ),
                            array(
                                'id' => 'active_on',
                                'value' => false,
                                'label' => $this->l('Enabled'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'radio',
                        'name' => 'mpgs_api_url',
                        'desc' => $this->l(''),
                        'label' => $this->l('API Endpoint'),
                        'values' => $apiOptions
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Custom API Endpoint'),
                        'name' => 'mpgs_api_url_custom',
                        'required' => true
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Send Line Items'),
                        'desc' => $this->l('Include line item details on gateway order'),
                        'name' => 'mpgs_lineitems_enabled',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_off',
                                'value' => true,
                                'label' => $this->l('Disabled'),
                            ),
                            array(
                                'id' => 'active_on',
                                'value' => false,
                                'label' => $this->l('Enabled'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Merchant ID'),
                        'name' => 'mpgs_merchant_id',
                        'required' => true
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('API Password'),
                        'name' => 'mpgs_api_password',
                        'required' => true
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('Webhook Secret'),
                        'name' => 'mpgs_webhook_secret',
                        'required' => false
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Test Merchant ID'),
                        'name' => 'test_mpgs_merchant_id',
                        'required' => true
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('Test API Password'),
                        'name' => 'test_mpgs_api_password',
                        'required' => true
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('Test Webhook Secret'),
                        'name' => 'test_mpgs_webhook_secret',
                        'required' => false
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * @return array
     */
    protected function getAdminAdvancedSettingForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Advanced Parameters'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('Logging Verbosity'),
                        'desc' => $this->l('Allows to set the verbosity level of var/logs/mastercard.log'),
                        'name' => 'mpgs_logging_level',
                        'options' => array(
                            'query' => array(
                                array('id' => \Monolog\Logger::DEBUG, 'name' => $this->l('Everything')),
                                array('id' => \Monolog\Logger::WARNING, 'name' => $this->l('Errors and Warning Only')),
                                array('id' => \Monolog\Logger::ERROR, 'name' => $this->l('Errors Only')),
                                array('id' => \Monolog\Logger::EMERGENCY, 'name' => $this->l('Disabled')),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Gateway Order ID Prefix'),
                        'desc' => $this->l('Should be specified in case multiple integrations use the same Merchant ID'),
                        'name' => 'mpgs_order_prefix',
                        'required' => false
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Custom Webhook Endpoint'),
                        'desc' => $this->l('Not required. If left blank, the value defaults to: ') . $this->context->link->getModuleLink($this->name, 'webhook', array(), true),
                        'name' => 'mpgs_webhook_url',
                        'required' => false
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            )
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getAdminFormValues();

        // Handles normal fields
        foreach ($form_values as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            if (in_array($key, ['mpgs_api_password', 'test_mpgs_api_password', 'mpgs_webhook_secret', 'test_mpgs_webhook_secret'])) {
                if (!$value) {
                    continue;
                }
            }
            Configuration::updateValue($key, $value);
        }

        // Handles translated fields
        $translatedFields = array(
            'mpgs_hc_title',
            'mpgs_hs_title'
        );
        $languages = Language::getLanguages(false);
        foreach ($translatedFields as $field) {
            $translatedValues = array();
            foreach ($languages as $lang) {
                if (Tools::getIsset($field.'_'.$lang['id_lang'])) {
                    $translatedValues[$lang['id_lang']] = Tools::getValue($field . '_' . $lang['id_lang']);
                }
            }
            Configuration::updateValue($field, $translatedValues);
        }

        $this->_html .= $this->displayConfirmation($this->l('Settings updated'));

        // Test the Gateway connection
        try {
            $client = new GatewayService(
                $this->getApiEndpoint(),
                $this->getApiVersion(),
                $this->getConfigValue('mpgs_merchant_id'),
                $this->getConfigValue('mpgs_api_password'),
                $this->getWebhookUrl()
            );
            $client->paymentOptionsInquiry();
            Configuration::updateValue('mpgs_gateway_validated', 1);
        } catch (Exception $e) {
            Configuration::updateValue('mpgs_gateway_validated', 0);
        }
    }

    /**
     * @param $params
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrderLeft($params)
    {
        return $this->renderActionsSections($params, 'views/templates/hook/order_actions.tpl');
    }

    /**
     * @param $params
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrderSideBottom($params)
    {
        return $this->renderActionsSections($params, 'views/templates/hook/order_actions_v1770.tpl');
    }

    /**
     * @param array $params
     */
    public function hookActionObjectOrderSlipAddAfter($params)
    {
        /** @var OrderSlip $res */
        $orderSlip = $params['object'];
        $order = new Order($orderSlip->id_order);

        if ($order->payment !== self::PAYMENT_CODE) {

            return;
        }

        $refundService = new MpgsRefundService($this);
        $amount = (string)($orderSlip->total_shipping_tax_incl + $orderSlip->total_products_tax_incl);

        if (!Tools::getValue('withdrawToCustomer')) {
            return;
        }

        try {
            $response = $refundService->execute(
                $order,
                array(
                    new TransactionResponseHandler()
                ),
                $amount,
                'partial-' . $orderSlip->id
            );

            $refund = new MpgsRefund();

            $refund->order_id = $order->id;
            $refund->total = $amount;
            $refund->transaction_id = $response['transaction']['id'];
            $refund->order_slip_id = $orderSlip->id;
            $refund->add();
        } catch (Exception $e) {
            $orderSlip->delete();
            Tools::redirectAdmin((new Link())->getAdminLink('AdminOrders', true, array(), array(
                'vieworder' => '',
                'id_order' => $order->id
            )));

            die();
        }
    }

    /**
     * @param $params
     * @param $view
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function renderActionsSections($params, $view)
    {
        if ($this->active == false) {
            return '';
        }

        $order = new Order($params['id_order']);
        if ($order->payment != self::PAYMENT_CODE) {
            return '';
        }

        $isAuthorized = $order->current_state == Configuration::get('MPGS_OS_AUTHORIZED');
        $canVoid = $isAuthorized;
        $canCapture = $isAuthorized;
        $canRefund = $order->current_state == Configuration::get('PS_OS_PAYMENT');
        $canReview = $order->current_state == Configuration::get('MPGS_OS_REVIEW_REQUIRED');

        $canAction = $isAuthorized || $canVoid || $canCapture || $canRefund;

        $this->smarty->assign(array(
            'module_dir' => $this->_path,
            'order' => $order,
            'mpgs_order_ref' => $this->getOrderRef($order),
            'can_void' => $canVoid,
            'can_capture' => $canCapture,
            'can_refund' => $canRefund && !MpgsRefund::hasExistingRefunds($order->id),
            'can_partial_refund' => !MpgsRefund::hasExistingFullRefund($order->id),
            'is_authorized' => $isAuthorized,
            'can_review' => $canReview,
            'can_action' => $canAction,
            'refunds' => MpgsRefund::getAllRefundsByOrderId($order->id),
        ));

        return $this->display(__FILE__, $view);
    }

    /**
     * @return array
     * @throws SmartyException
     * @throws Exception
     */
    public function hookPaymentOptions()
    {
        if (!$this->active) {
            return array();
        }

        $this->context->smarty->assign(array(
            'mpgs_config' => array(
                'merchant_id' => $this->getConfigValue('mpgs_merchant_id'),
                'amount' => $this->context->cart->getOrderTotal(),
                'currency' => $this->context->currency->iso_code,
                'order_id' => $this->getNewOrderRef(),
            ),
        ));

        $methods = array();

        if (Configuration::get('mpgs_hc_active') && Configuration::get('mpgs_gateway_validated')) {
            $methods[] = $this->getHostedCheckoutPaymentOption();
        }

        if (Configuration::get('mpgs_hs_active') && Configuration::get('mpgs_gateway_validated')) {
            $methods[] = $this->getHostedSessionPaymentOption();
        }

        return $methods;
    }

    /**
     * @param $field
     * @return string|false
     */
    public function getConfigValue($field)
    {
        $testPrefix = '';
        if (!Configuration::get('mpgs_mode')) {
            $testPrefix = 'test_';
        }

        return Configuration::get($testPrefix . $field);
    }

    /**
     * @return PaymentOption
     * @throws SmartyException
     */
    protected function getHostedSessionPaymentOption()
    {
        $form = $this->generateHostedSessionForm();

        $option = new PaymentOption();
        $option
            ->setModuleName($this->name . '_hs')
            ->setCallToActionText(Configuration::get('mpgs_hs_title', $this->context->language->id))
            ->setAdditionalInformation($this->context->smarty->fetch('module:mastercard/views/templates/front/methods/hostedsession.tpl'))
            ->setForm($form);

        return $option;
    }

    /**
     * @return PaymentOption
     * @throws SmartyException
     */
    protected function getHostedCheckoutPaymentOption()
    {
        $form = $this->generateHostedCheckoutForm();

        $option = new PaymentOption();
        $option
            ->setModuleName($this->name . '_hc')
            ->setCallToActionText(Configuration::get('mpgs_hc_title', $this->context->language->id))
            //->setAdditionalInformation($this->context->smarty->fetch('module:mastercard/views/templates/front/methods/hostedcheckout.tpl'))
            ->setForm($form);

        return $option;
    }

    /**
     * @return string
     * @throws SmartyException
     * @throws Exception
     */
    protected function generateHostedSessionForm()
    {
        $this->context->smarty->assign(array(
            'hostedsession_action_url' => $this->context->link->getModuleLink($this->name, 'hostedsession', array(), true),
            'hostedsession_component_url' => $this->getHostedSessionJsComponent(),
            'hostedsession_3ds' => Configuration::get('mpgs_hs_3ds'),
        ));

        return $this->context->smarty->fetch('module:mastercard/views/templates/front/methods/hostedsession/form.tpl');
    }

    /**
     * @return string
     * @throws SmartyException
     * @throws Exception
     */
    protected function generateHostedCheckoutForm()
    {
        $this->context->smarty->assign(array(
            'hostedcheckout_action_url' => $this->context->link->getModuleLink($this->name, 'hostedcheckout', array(), true),
            'hostedcheckout_cancel_url' => $this->context->link->getModuleLink($this->name, 'hostedcheckout', array('cancel' => 1), true),
            'hostedcheckout_component_url' => $this->getHostedCheckoutJsComponent(),
        ));
        return $this->context->smarty->fetch('module:mastercard/views/templates/front/methods/hostedcheckout/form.tpl');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getApiEndpoint()
    {
        $endpoint = Configuration::get('mpgs_api_url');
        if (!$endpoint) {
            $endpoint = Configuration::get('mpgs_api_url_custom');
        }

        if (!$endpoint) {
            throw new Exception("API endpoint not specified.");
        }

        return $endpoint;
    }

    /**
     * @return string
     * @throws Exception
     * https://mtf.gateway.mastercard.com/checkout/version/50/checkout.js
     */
    public function getHostedCheckoutJsComponent()
    {
        $cacheBust = (int) round(microtime(true));
        return 'https://'. $this->getApiEndpoint() . '/checkout/version/' . $this->getApiVersion() . '/checkout.js?_=' . $cacheBust;
    }

    /**
     * @return string
     * @throws Exception
     * https://mtf.gateway.mastercard.com/form/version/50/merchant/<MERCHANTID>/session.js
     */
    public function getHostedSessionJsComponent()
    {
        $cacheBust = (int) round(microtime(true));
        return 'https://'. $this->getApiEndpoint() . '/form/version/' . $this->getApiVersion() . '/merchant/' . $this->getConfigValue('mpgs_merchant_id') . '/session.js?_=' . $cacheBust;
    }


    /**
     * @return string
     */
    public function getWebhookUrl()
    {
        return Configuration::get('mpgs_webhook_url') ?
            : $this->context->link->getModuleLink($this->name, 'webhook', array(), true);
    }

    /**
     * @param string|int $cartId
     * @param false $refresh
     * @return string
     */
    private function getOrderSuffix($cartId, $refresh = false)
    {
        $suffixModel = MpgsOrderSuffix::getOrderSuffixByOrderId($cartId, $refresh);
        return $suffixModel ? '-' . $suffixModel->suffix : '';
    }

    /**
     * @param Order $order
     * @return string
     */
    public function getOrderRef($order)
    {
        $cartId = (string) $order->id_cart;
        $suffix = $this->getOrderSuffix($cartId);
        $prefix = Configuration::get('mpgs_order_prefix')?:'';

        return $prefix . $cartId . $suffix;
    }

    /**
     * @param bool $refreshSuffix
     * @return string
     */
    public function getNewOrderRef($refreshSuffix = false)
    {
        $cartId = (string) Context::getContext()->cart->id;
        $suffix = $this->getOrderSuffix($cartId, $refreshSuffix);
        $prefix = Configuration::get('mpgs_order_prefix')?:'';

        return $prefix . $cartId . $suffix;
    }

    /**
     * @param Order $order
     * @param string $txnId
     * @return OrderPayment|null
     */
    public function getTransactionById($order, $txnId)
    {
        foreach ($order->getOrderPayments() as $payment) {
            if ($payment->transaction_id == $txnId) {
                return $payment;
            }
        }

        return null;
    }


    /**
     * @return array|null
     */
    public function getOrderItems()
    {
        if (!Configuration::get('mpgs_lineitems_enabled')) {
            return null;
        }

        $items = $this->context->cart->getProducts(false, false, $this->context->country->id, true);
        $cartItems = array();

        /** @var Product $item */
        foreach ($items as $item) {
            $cartItems[] = array(
                'name' => GatewayService::safe($item['name'], 127),
                'quantity' => GatewayService::numeric($item['cart_quantity']),
                'sku' => GatewayService::safe($item['reference'], 127),
                'unitPrice' => GatewayService::numeric($item['price_wt']),
            );
        }

        return empty($cartItems) ? null : $cartItems;
    }

    /**
     * @return string|null
     * @throws Exception
     */
    public function getShippingHandlingAmount()
    {
        if (!Configuration::get('mpgs_lineitems_enabled')) {
            return null;
        }

        $total = Context::getContext()->cart->getOrderTotal();

        return GatewayService::numeric(
            $total - (float)$this->getItemAmount()
        );
    }

    /**
     * @return string|null
     */
    public function getItemAmount()
    {
        $items = $this->getOrderItems();

        if (!$items) {
            return null;
        }

        $amount = 0.0;
        foreach ($items as $item) {
            $amount += (float)$item['unitPrice'] * (float)$item['quantity'];
        }

        return GatewayService::numeric($amount);
    }
}
