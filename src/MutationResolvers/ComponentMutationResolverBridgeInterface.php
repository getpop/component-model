<?php

declare(strict_types=1);

namespace PoP\ComponentModel\MutationResolvers;

interface ComponentMutationResolverBridgeInterface
{
    /**
     * @param array $data_properties
     * @return array<string, mixed>|null
     */
    public function execute(array &$data_properties): ?array;
}
