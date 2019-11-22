<?php
namespace PoP\ComponentModel\Engine;

interface DataloadingEngineInterface
{
    public function getMandatoryDirectiveClasses(): array;
    public function addMandatoryDirectiveClass(string $directiveClass): void;
    public function addMandatoryDirectiveClasses(array $directiveClasses): void;
}
