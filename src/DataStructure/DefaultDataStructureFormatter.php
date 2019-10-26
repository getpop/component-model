<?php
namespace PoP\ComponentModel\DataStructure;

class DefaultDataStructureFormatter extends AbstractDataStructureFormatter
{
    public const NAME = 'default';

    public static function getName() {
        return self::NAME;
    }
}
