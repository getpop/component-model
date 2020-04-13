<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Facades\Container;

use PoP\ComponentModel\Container\ObjectDictionaryInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ObjectDictionaryFacade
{
    public static function getInstance(): ObjectDictionaryInterface
    {
        return ContainerBuilderFactory::getInstance()->get('object_dictionary');
    }
}
