<?php
namespace PoP\ComponentModel\Container;

use PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups;
use PoP\Root\Container\ContainerBuilderUtils as RootContainerBuilderUtils;

class ContainerBuilderUtils {

    /**
     * Attach all fieldValueResolvers located under the specified namespace
     *
     * @param string $namespace
     * @return void
     */
    public static function attachFieldValueResolversFromNamespace(string $namespace): void
    {
        foreach (RootContainerBuilderUtils::getNamespaceServiceIds($namespace) as $serviceClass) {
            $serviceClass::attach(AttachableExtensionGroups::FIELDVALUERESOLVERS);
        }
    }
}
