<?php
namespace PoP\ComponentModel\ModuleProcessors;

abstract class AbstractModuleDecoratorProcessor implements ModuleDecoratorProcessorInterface
{
    use ModulePathProcessorTrait;

    //-------------------------------------------------
    // PROTECTED Functions
    //-------------------------------------------------

    final protected function getModuleProcessor(array $module)
    {
        return $this->getModuleProcessordecorator($module);
    }

    final protected function getModuleProcessordecorator(array $module)
    {
        $processor = $this->getDecoratedmoduleProcessor($module);
        return $this->getModuledecoratorprocessorManager()->getProcessordecorator($processor);
    }

    final protected function getDecoratedmoduleProcessor(array $module)
    {
        $moduleprocessor_manager = ModuleProcessorManagerFactory::getInstance();
        return $moduleprocessor_manager->getProcessor($module);
    }

    protected function getModuledecoratorprocessorManager()
    {
        return null;
    }

    //-------------------------------------------------
    // PUBLIC Functions
    //-------------------------------------------------
    final public function getAllSubmodules(array $module): array
    {
        $moduleprocessor_manager = ModuleProcessorManagerFactory::getInstance();
        $processor = $moduleprocessor_manager->getProcessor($module);
        return $processor->getAllSubmodules($module);
    }
}
