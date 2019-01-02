<?php
/**
 * Copyright (c) On Tap Networks Limited.
 */

namespace OnTap\Mpgs;


class App
{
    /**
     * @var array
     */
    protected $context;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Form\FormGenerator
     */
    protected $formGenerator;

    /**
     * @var App
     */
    public static $instance;

    /**
     * App constructor.
     */
    public function __construct()
    {
        $module = \Module::getInstanceByName('mastercard');

        $this->context = array(
            'module' => $module,
            'app' => $this
        );

        $this->config = new Config($this->context);
        $this->formGenerator = new Form\FormGenerator($this->context);
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return Form\FormGenerator
     */
    public function getAdminForm()
    {
        return $this->formGenerator;
    }

    /**
     * @return App
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
