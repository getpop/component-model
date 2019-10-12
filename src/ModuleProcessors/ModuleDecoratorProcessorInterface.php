<?php
namespace PoP\ComponentModel\ModuleProcessors;

interface ModuleDecoratorProcessorInterface
{
    public function getAllSubmodules(array $module): array;
}
