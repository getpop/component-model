<?php
namespace PoP\ComponentModel\Engine;

class DataloadingEngine implements DataloadingEngineInterface
{
    protected $mandatoryRootDirectiveClasses = [];
    protected $mandatoryRootDirectives = [];

    public function getMandatoryRootDirectiveClasses(): array
    {
        return $this->mandatoryRootDirectiveClasses;
    }
    public function getMandatoryRootDirectives(): array
    {
        return $this->mandatoryRootDirectives;
    }

    public function addMandatoryRootDirectiveClass(string $directiveClass): void
    {
        $this->mandatoryRootDirectiveClasses[] = $directiveClass;
    }
    public function addMandatoryRootDirective(string $directive): void
    {
        $this->mandatoryRootDirectives[] = $directive;
    }

    public function addMandatoryRootDirectiveClasses(array $directiveClasses): void
    {
        $this->mandatoryRootDirectiveClasses = array_merge(
            $this->mandatoryRootDirectiveClasses,
            $directiveClasses
        );
    }
    public function addMandatoryRootDirectives(array $directives): void
    {
        $this->mandatoryRootDirectives = array_merge(
            $this->mandatoryRootDirectives,
            $directives
        );
    }
}
