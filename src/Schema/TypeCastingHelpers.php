<?php
namespace PoP\ComponentModel\Schema;

class TypeCastingHelpers
{
    public static function combineTypes(...$types) {
        return implode(':', $types);
    }
    /**
     * Return the current type combination element, which is simply the first element, always
     * Eg: if passing "string", it is "string"; for "array:string", it is "array";
     *
     * @param [type] $type
     * @return void
     */
    public static function getTypeCombinationCurrentElement(string $type): string {
        $maybeCombinationElems = explode(':', $type);
        return $maybeCombinationElems[0];
    }
    /**
     * If the type is a combination of 2 or more types, then return the string containing all of them except the first one
     * Eg: if passing "string", it is null; for "array:string", it is "string"; for "array:array:string", it is "array:string"
     *
     * @param [type] $type
     * @return void
     */
    public static function getTypeCombinationNestedElements(string $type): ?string {
        $maybeCombinationElems = explode(':', $type);
        if (count($maybeCombinationElems) >= 2) {
            // Remove the first element
            return substr($type, strlen($maybeCombinationElems[0])+1);
        }
        // There are no others
        return null;
    }
}
