<?php
namespace PoP\ComponentModel\Container;

class ObjectDictionary implements ObjectDictionaryInterface
{

    protected $dictionary;

    public function get(string $class, $id): ?object
    {
        return $this->dictionary[$class][$id];
    }
    public function has(string $class, $id): bool
    {
        return isset($this->dictionary[$class][$id]);
    }
    public function set(string $class, $id, object $instance): void
    {
        $this->dictionary[$class][$id] = $instance;
    }
}
