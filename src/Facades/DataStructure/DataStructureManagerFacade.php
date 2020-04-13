<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Facades\DataStructure;

use PoP\ComponentModel\DataStructure\DataStructureManagerInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class DataStructureManagerFacade
{
    public static function getInstance(): DataStructureManagerInterface
    {
        return ContainerBuilderFactory::getInstance()->get('data_structure_manager');
    }
}
