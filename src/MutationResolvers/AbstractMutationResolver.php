<?php

declare(strict_types=1);

namespace PoP\ComponentModel\MutationResolvers;

abstract class AbstractMutationResolver implements MutationResolverInterface
{
    public function validate(array $form_data): ?array
    {
        return null;
    }

    public function getErrorType(): int
    {
        return ErrorTypes::DESCRIPTIONS;
    }
}
