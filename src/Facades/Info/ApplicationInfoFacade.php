<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Facades\Info;

use PoP\ComponentModel\Info\ApplicationInfoInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ApplicationInfoFacade
{
    public static function getInstance(): ApplicationInfoInterface
    {
        return ContainerBuilderFactory::getInstance()->get('application_info');
    }
}
