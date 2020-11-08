<?php

declare(strict_types=1);

namespace PoP\ComponentModel\MutationResolvers;

interface MutationResolverInterface
{
    /**
     * @return mixed|null
     */
    public function execute(array $form_data);
    public function validate(array $form_data): ?array;
    public function getErrorType(): int;
}
