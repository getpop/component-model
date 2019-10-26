<?php
namespace PoP\ComponentModel\Instances;

interface InstanceManagerInterface
{
    public function getInstance(string $class);
}
