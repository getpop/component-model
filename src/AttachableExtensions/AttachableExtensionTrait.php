<?php
namespace PoP\ComponentModel\AttachableExtensions;

use PoP\ComponentModel\Facades\AttachableExtensions\AttachableExtensionManagerFacade;

trait AttachableExtensionTrait
{
    /**
     * It is represented through a static class, because the extensions work at class level, not object level
     */
    public static function getClassesToAttachTo(): array
    {
        return [];
    }

    public static function attach(string $group, int $priority = 10)
    {
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();
        $extensionClass = get_called_class();
        foreach ($extensionClass::getClassesToAttachTo() as $attachableClass) {
            $attachableExtensionManager->addExtensionClass(
                $attachableClass,
                $group,
                $extensionClass,
                $priority
            );
        }
    }
}
