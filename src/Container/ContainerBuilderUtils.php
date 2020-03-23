<?php
namespace PoP\ComponentModel\Container;

use PoP\Root\Container\ContainerBuilderFactory;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups;
use PoP\Root\Container\ContainerBuilderUtils as RootContainerBuilderUtils;

class ContainerBuilderUtils extends RootContainerBuilderUtils {

    /**
     * Register all typeResolvers located under the specified namespace
     *
     * @param string $namespace
     * @return void
     */
    public static function registerTypeResolversFromNamespace(string $namespace, bool $includeSubfolders = true): void
    {
        // If cached, do not execute or it will throw exception
        if (!ContainerBuilderFactory::isCached()) {
            foreach (self::getServiceClassesUnderNamespace($namespace, $includeSubfolders) as $serviceClass) {
                self::injectValuesIntoService(
                    'type_registry',
                    'addTypeResolverClass',
                    $serviceClass
                );
            }
        }
    }

    /**
     * Attach all fieldResolvers located under the specified namespace
     *
     * @param string $namespace
     * @return void
     */
    public static function attachFieldResolversFromNamespace(string $namespace, bool $includeSubfolders = true, int $priority = 10): void
    {
        foreach (self::getServiceClassesUnderNamespace($namespace, $includeSubfolders) as $serviceClass) {
            $serviceClass::attach(AttachableExtensionGroups::FIELDRESOLVERS, $priority);
        }
    }

    /**
     * Attach all directiveResolvers located under the specified namespace
     *
     * @param string $namespace
     * @return void
     */
    public static function attachAndRegisterDirectiveResolversFromNamespace(string $namespace, bool $includeSubfolders = true, int $priority = 10): void
    {
        $isContainerCached = ContainerBuilderFactory::isCached();
        foreach (self::getServiceClassesUnderNamespace($namespace, $includeSubfolders) as $serviceClass) {
            $serviceClass::attach(AttachableExtensionGroups::DIRECTIVERESOLVERS, $priority);

            // Register the directive in the registry. If cached, do not execute or it will throw exception
            if (!$isContainerCached) {
                self::injectValuesIntoService(
                    'directive_registry',
                    'addDirectiveResolverClass',
                    $serviceClass
                );
            }
        }
    }

    /**
     * Attach all typeResolverPickers located under the specified namespace
     *
     * @param string $namespace
     * @return void
     */
    public static function attachTypeResolverPickersFromNamespace(string $namespace, bool $includeSubfolders = true, int $priority = 10): void
    {
        foreach (self::getServiceClassesUnderNamespace($namespace, $includeSubfolders) as $serviceClass) {
            $serviceClass::attach(AttachableExtensionGroups::TYPERESOLVERPICKERS, $priority);
        }
    }

    /**
     * Attach all typeResolverDecorators located under the specified namespace
     *
     * @param string $namespace
     * @return void
     */
    public static function attachTypeResolverDecoratorsFromNamespace(string $namespace, bool $includeSubfolders = true, int $priority = 10): void
    {
        foreach (self::getServiceClassesUnderNamespace($namespace, $includeSubfolders) as $serviceClass) {
            $serviceClass::attach(AttachableExtensionGroups::TYPERESOLVERDECORATORS, $priority);
        }
    }
}
