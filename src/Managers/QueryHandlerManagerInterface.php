<?php
namespace PoP\ComponentModel\Managers;

interface QueryHandlerManagerInterface
{
    public function add($name, $queryhandler);
    public function get($name);
}
