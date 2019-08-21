<?php
namespace PoP\ComponentModel\Managers;

interface ModulePathHelpersInterface
{
    public function getStringifiedModulePropagationCurrentPath(array $module);
    public function stringifyModulePath(array $modulepath);
    public function recastModulePath(string $modulepath_as_string);
}
