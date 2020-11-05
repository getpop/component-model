<?php

declare(strict_types=1);

namespace PoP\ComponentModel\MutationResolvers;

interface MutationResolverInterface
{
    /**
     * @return mixed|null
     */
    public function execute(array &$errors);
}
