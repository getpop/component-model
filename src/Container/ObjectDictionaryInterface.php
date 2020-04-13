<?php
namespace PoP\ComponentModel\Container;

interface ObjectDictionaryInterface
{

    public function get(string $class, $id): ?object;
    public function has(string $class, $id): bool;
    public function set(string $class, $id, object $instance): void;
}
