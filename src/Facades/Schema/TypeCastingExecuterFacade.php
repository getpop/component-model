<?php
namespace PoP\ComponentModel\Facades\Schema;

use PoP\ComponentModel\Schema\TypeCastingExecuterInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class TypeCastingExecuterFacade
{
    public static function getInstance(): TypeCastingExecuterInterface
    {
        return ContainerBuilderFactory::getInstance()->get('type_casting_executer');
    }
}
