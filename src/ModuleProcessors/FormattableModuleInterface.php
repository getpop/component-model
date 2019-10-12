<?php
namespace PoP\ComponentModel\ModuleProcessors;

interface FormattableModuleInterface
{
    public function getFormat(array $module): ?string;
}
