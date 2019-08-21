<?php
namespace PoP\ComponentModel\Managers;

interface InstanceManagerInterface
{
    public function getInstance(string $class);
}
