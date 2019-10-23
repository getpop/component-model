<?php
namespace PoP\ComponentModel\Config;

use PoP\ComponentModel\Container\ContainerBuilderUtils;
use PoP\Root\Component\PHPServiceConfigurationTrait;
use PoP\ComponentModel\Configuration\Request;

class ServiceConfiguration
{
    use PHPServiceConfigurationTrait;

    protected static function configure()
    {
        // If `isMangled`, disable the definitions
        if (!Request::isMangled()) {
            ContainerBuilderUtils::injectValuesIntoService(
                'definition_manager',
                'setEnabled',
                false
            );
        }
    }
}
