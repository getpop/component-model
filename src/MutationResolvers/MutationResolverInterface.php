<?php

declare(strict_types=1);

namespace PoP\ComponentModel\MutationResolvers;

interface MutationResolverInterface
{
    /**
     * @param string[] $errors
     * @return mixed|null
     */
    public function execute(array &$errors, array &$errorcodes);
}
