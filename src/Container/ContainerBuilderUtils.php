<?php
namespace PoP\ComponentModel\Container;

use PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups;
use PoP\Root\Container\ContainerBuilderUtils as RootContainerBuilderUtils;

class ContainerBuilderUtils extends RootContainerBuilderUtils {

    /**
     * Attach all fieldValueResolvers located under the specified namespace
     *
     * @param string $namespace
     * @return void
     */
    public static function attachFieldValueResolversFromNamespace(string $namespace, bool $includeSubfolders = true): void
    {
        foreach (self::getServiceClassesUnderNamespace($namespace, $includeSubfolders) as $serviceClass) {
            $serviceClass::attach(AttachableExtensionGroups::FIELDVALUERESOLVERS);
        }
    }

    /**
     * Attach all directiveResolvers located under the specified namespace
     *
     * @param string $namespace
     * @return void
     */
    public static function attachDirectiveResolversFromNamespace(string $namespace, bool $includeSubfolders = true): void
    {
        foreach (self::getServiceClassesUnderNamespace($namespace, $includeSubfolders) as $serviceClass) {
            $serviceClass::attach(AttachableExtensionGroups::FIELDDIRECTIVERESOLVERS);
        }
    }
}
