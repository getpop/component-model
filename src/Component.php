<?php
namespace PoP\ComponentModel;

use PoP\Root\Component\AbstractComponent;
use PoP\Root\Component\YAMLServicesTrait;

/**
 * Initialize component
 */
class Component extends AbstractComponent
{
    use YAMLServicesTrait;
    const VERSION = '0.1.0';

    /**
     * Initialize services
     */
    public static function init()
    {
        parent::init();
        self::initYAMLServices(dirname(__DIR__));
    }
}
