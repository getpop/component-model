<?php
namespace PoP\ComponentModel\Engine;

interface DataloadingEngineInterface
{
    public function getMandatoryRootDirectiveClasses(): array;
    public function addMandatoryRootDirectiveClass(string $directiveClass): void;
    public function addMandatoryRootDirectiveClasses(array $directiveClasses): void;
}
