<?php

declare(strict_types=1);

namespace PoP\ComponentModel;

use PoP\Root\Component\AbstractComponent;
use PoP\Root\Component\YAMLServicesTrait;
use PoP\ComponentModel\Config\ServiceConfiguration;
use PoP\ComponentModel\Container\ContainerBuilderUtils;

/**
 * Initialize component
 */
class Component extends AbstractComponent
{
    use YAMLServicesTrait;
    // const VERSION = '0.1.0';

    public static function getDependedComponentClasses(): array
    {
        return [
            \PoP\Definitions\Component::class,
            \PoP\FieldQuery\Component::class,
        ];
    }

    public static function getDependedMigrationPlugins(): array
    {
        return [
            'migrate-component-model',
        ];
    }

    /**
     * Initialize services
     */
    protected static function doInitialize(bool $skipSchema = false): void
    {
        parent::doInitialize($skipSchema);
        self::initYAMLServices(dirname(__DIR__));
        ServiceConfiguration::initialize();
    }

    /**
     * Boot component
     *
     * @return void
     */
    public static function beforeBoot(): void
    {
        parent::beforeBoot();

        // Initialize the Component Configuration
        ComponentConfiguration::init();

        // Initialize classes
        ContainerBuilderUtils::attachFieldResolversFromNamespace(__NAMESPACE__ . '\\FieldResolvers');
        ContainerBuilderUtils::attachAndRegisterDirectiveResolversFromNamespace(__NAMESPACE__ . '\\DirectiveResolvers');
    }
}
