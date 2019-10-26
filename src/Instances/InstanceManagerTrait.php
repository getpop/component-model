<?php
namespace PoP\ComponentModel\Instances;
use PoP\Root\Container\ContainerBuilderFactory;

trait InstanceManagerTrait
{
    private $instances = [];
    private $overridingClasses = [];

    public function overrideClass(string $overrideClass, string $withClass): void
    {
        $this->overridingClasses[$overrideClass] = $withClass;
    }

    protected function hasClassBeenLoaded(string $class)
    {
        return !is_null($this->instances[$class]);
    }

    public function getClassInstance(string $class)
    {
        if (!$this->hasClassBeenLoaded($class)) {
            // Allow a class to take the place of another one
            if ($overridingClass = $this->overridingClasses[$class]) {
                $class = $overridingClass;
            }

            // First ask the ContainerBuilder to handle the class as a Service
            $containerBuilder = ContainerBuilderFactory::getInstance();
            if ($containerBuilder->has($class)) {
                $instance = $containerBuilder->get($class);
            } else {
                // Otherwise, assume the class needs no parameters
                $instance = new $class();
            }
            $this->instances[$class] = $instance;
        }

        return $this->instances[$class];
    }

    public function getInstance(string $class)
    {
        return $this->getClassInstance($class);
    }
}
