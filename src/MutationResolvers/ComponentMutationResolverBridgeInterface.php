<?php

declare(strict_types=1);

namespace PoP\ComponentModel\MutationResolvers;

interface ComponentMutationResolverBridgeInterface
{
    public function execute(&$data_properties);
}
