<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Resolvers;

interface TypeOrFieldInterfaceResolverInterface
{
    public function getTypeOrFieldInterfaceName(): string;
}
