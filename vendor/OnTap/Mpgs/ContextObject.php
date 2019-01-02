<?php
/**
 * Copyright (c) On Tap Networks Limited.
 */

namespace OnTap\Mpgs;


class ContextObject
{
    /**
     * @var \ModuleCore
     */
    protected $module;

    /**
     * @var \OnTap\Mpgs\App
     */
    protected $app;

    /**
     * Config constructor.
     * @param array $context
     */
    public function __construct($context)
    {
        $this->module = $context['module'];
        $this->app = $context['app'];
    }

    /**
     * @return mixed|\ModuleCore
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @return mixed|App
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Translate string
     * @param $str
     * @param bool $specific
     * @param null $locale
     * @return string
     */
    public function __($str, $specific = false, $locale = null)
    {
        return $this->getModule()->l($str, $specific, $locale);
    }
}
