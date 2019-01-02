<?php
/**
 * Copyright (c) On Tap Networks Limited.
 */

namespace OnTap\Mpgs;

class Config extends ContextObject
{
    const _MPGS_PREFIX_ = 'mpgs_';
    const _TEST_PREFIX_ = 'test_';

    /**
     * @return array
     */
    public function getApiUrls()
    {
        return array(
            'https://eu-gateway.mastercard.com/' => $this->__('https://eu-gateway.mastercard.com/'),
            'https://ap-gateway.mastercard.com/' => $this->__('https://ap-gateway.mastercard.com/'),
            'https://na-gateway.mastercard.com/' => $this->__('https://na-gateway.mastercard.com/'),
            'https://mtf.gateway.mastercard.com/' => $this->__('https://mtf.gateway.mastercard.com/'),
        );
    }

    /**
     * @return array
     */
    public function getConfigFormValues()
    {
        return array(
            'MASTERCARD_LIVE_MODE' => \Configuration::get('MASTERCARD_LIVE_MODE', true),
            self::_MPGS_PREFIX_.'api_url' => \Configuration::get(self::_MPGS_PREFIX_.'api_url', null),
            self::_MPGS_PREFIX_.'merchant_id' => \Configuration::get(self::_MPGS_PREFIX_.'merchant_id', null),
            self::_MPGS_PREFIX_.'api_password' => \Configuration::get(self::_MPGS_PREFIX_.'api_password', null),
            self::_TEST_PREFIX_.self::_MPGS_PREFIX_.'merchant_id' => \Configuration::get(self::_TEST_PREFIX_.self::_MPGS_PREFIX_.'merchant_id', null),
            self::_TEST_PREFIX_.self::_MPGS_PREFIX_.'api_password' => \Configuration::get(self::_TEST_PREFIX_.self::_MPGS_PREFIX_.'api_password', null),
        );
    }
}
