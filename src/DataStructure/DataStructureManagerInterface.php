<?php
namespace PoP\ComponentModel\DataStructure;

interface DataStructureManagerInterface
{
    public function add(DataStructureFormatterInterface $formatter): void;
    public function getDataStructureFormatter(string $name = null): DataStructureFormatterInterface;
}
