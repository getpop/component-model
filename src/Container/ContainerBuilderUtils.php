<?php
namespace PoP\ComponentModel\Container;

use PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups;
use PoP\Root\Container\ContainerBuilderUtils as RootContainerBuilderUtils;

class ContainerBuilderUtils extends RootContainerBuilderUtils {

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
    public static function attachDirectiveResolversFromNamespace(string $namespace, bool $includeSubfolders = true, int $priority = 10): void
    {
        foreach (self::getServiceClassesUnderNamespace($namespace, $includeSubfolders) as $serviceClass) {
            $serviceClass::attach(AttachableExtensionGroups::DIRECTIVERESOLVERS, $priority);
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
