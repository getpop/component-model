<?php
namespace PoP\ComponentModel\Config;

use PoP\ComponentModel\Container\ContainerBuilderUtils;
use PoP\Root\Component\PHPServiceConfigurationTrait;
use PoP\ComponentModel\Server\Utils;

class ServiceConfiguration
{
    use PHPServiceConfigurationTrait;

    protected static function configure()
    {
        // If `isMangled`, disable the definitions
        if (!Utils::isMangled()) {
            ContainerBuilderUtils::injectValueIntoService(
                'definition_manager',
                'setEnabled',
                false
            );
        }
    }
}
