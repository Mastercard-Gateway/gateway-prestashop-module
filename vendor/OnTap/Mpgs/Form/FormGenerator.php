<?php

/**
 * Copyright (c) On Tap Networks Limited.
 */

namespace OnTap\Mpgs\Form;

use OnTap\Mpgs\ContextObject;

class FormGenerator extends ContextObject
{
    /**
     * @return array
     */
    protected function getApiUrlFormField()
    {
        $fields = array(
            'type' => 'radio',
            'name' => \OnTap\Mpgs\Config::_MPGS_PREFIX_.'api_url',
            'desc' => $this->__(''),
            'label' => $this->__('API Endpoint'),
            'values' => array()
        );

        $c = 0;
        $config = \OnTap\Mpgs\App::getInstance()->getConfig();
        foreach ($config->getApiUrls() as $url => $label) {
            $fields['values'][] = array(
                'id' => 'api_' . $c,
                'value' => $url,
                'label' => $label,
            );
            $c++;
        }

        return $fields;
    }

    /**
     * Create the structure of your form.
     */
    public function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->__('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    $this->getApiUrlFormField(),
                    array(
                        'type' => 'text',
                        'label' => $this->__('Merchant ID'),
                        'name' => \OnTap\Mpgs\Config::_MPGS_PREFIX_.'merchant_id',
                        'size' => 20,
                        'id' => 'merchant_id',
                        'class' => 'fixed-width-xxl',
                        'required' => true
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->__('API Password'),
                        'name' => \OnTap\Mpgs\Config::_MPGS_PREFIX_.'api_password',
                        'size' => 20,
                        'id' => 'api_password',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->__('Test Merchant ID'),
                        'name' => \OnTap\Mpgs\Config::_TEST_PREFIX_.\OnTap\Mpgs\Config::_MPGS_PREFIX_.'merchant_id',
                        'size' => 20,
                        'id' => 'test_merchant_id',
                        'class' => 'fixed-width-xxl',
                        'required' => true
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->__('Test API Password'),
                        'name' => \OnTap\Mpgs\Config::_TEST_PREFIX_.\OnTap\Mpgs\Config::_MPGS_PREFIX_.'api_password',
                        'size' => 20,
                        'id' => 'test_api_password',
                        'required' => true
                    ),
                ),
                'submit' => array(
                    'title' => $this->__('Save'),
                ),
            ),
        );
    }
}
