<?php
namespace PoP\ComponentModel\Schema;

class TypeCastingHelpers
{
    public static function combineTypes(...$types) {
        return implode(':', $types);
    }
    /**
     * If the type is a combination of 2 or more types, then return an array containing these element types
     * Otherwise return null
     *
     * @param [type] $type
     * @return void
     */
    public static function maybeGetTypeCombinationElements($type): ?array {
        $maybeCombinationElems = explode(':', $type);
        if (count($maybeCombinationElems) >= 2) {
            return $maybeCombinationElems;
        }
        return null;
    }
}
