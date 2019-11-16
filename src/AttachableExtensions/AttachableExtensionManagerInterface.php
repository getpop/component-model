<?php
namespace PoP\ComponentModel\AttachableExtensions;

interface AttachableExtensionManagerInterface
{
    public function setExtensionClass(string $attachableClass, string $group, string $extensionClass, int $priority = 10);
    public function getExtensionClasses(string $attachableClass, string $group): array;
}
