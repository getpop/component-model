<?php

declare(strict_types=1);

namespace PoP\ComponentModel\ErrorHandling;

interface ErrorServiceInterface
{
    /**
     * @param string[]|null $path
     * @return array<string, mixed>
     */
    public function getErrorOutput(Error $error, ?array $path = null): array;
}
