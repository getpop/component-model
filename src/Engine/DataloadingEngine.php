<?php
namespace PoP\ComponentModel\Engine;

class DataloadingEngine implements DataloadingEngineInterface
{
    protected $mandatoryRootDirectives = [];

    public function getMandatoryRootDirectiveClasses(): array
    {
        return $this->mandatoryRootDirectives;
    }

    public function addMandatoryRootDirectiveClass(string $directiveClass): void
    {
        $this->mandatoryRootDirectives[] = $directiveClass;
    }

    public function addMandatoryRootDirectiveClasses(array $directiveClasses): void
    {
        $this->mandatoryRootDirectives = array_merge(
            $this->mandatoryRootDirectives,
            $directiveClasses
        );
    }
}
