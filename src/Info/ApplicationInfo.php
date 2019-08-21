<?php
namespace PoP\ComponentModel\Info;

class ApplicationInfo implements ApplicationInfoInterface
{
    private $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
