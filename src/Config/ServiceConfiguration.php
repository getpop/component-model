<?php
namespace PoP\ComponentModel\Config;

use PoP\ComponentModel\Container\ContainerBuilderUtils;
use PoP\Root\Component\PHPServiceConfigurationTrait;
use PoP\ComponentModel\Configuration\Request;
use PoP\ComponentModel\DirectiveResolvers\ValidateDirectiveResolver;
use PoP\ComponentModel\DirectiveResolvers\ResolveValueAndMergeDirectiveResolver;

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

        // Inject the mandatory root directives
        ContainerBuilderUtils::injectValuesIntoService(
            'dataloading_engine',
            'addMandatoryDirectiveClasses',
            [
                ValidateDirectiveResolver::class,
                ResolveValueAndMergeDirectiveResolver::class,
            ]
        );
    }
}
