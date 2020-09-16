<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Info;

class ApplicationInfo implements ApplicationInfoInterface
{
    private string $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
