<?php
namespace PoP\ComponentModel\Schema;
use PoP\ComponentModel\GeneralUtils;

class QueryUtils
{
    public static function findFirstSymbolPosition(string $haystack, string $needle, $skipFromChars = '', $skipUntilChars = '')
    {
        // Edge case: If the string starts with the symbol, then the array count of splitting the elements will be 1
        if (substr($haystack, 0, strlen($needle)) == $needle) {
            return 0;
        }
        // Split on that searching element: If it appears within the string, it will produce an array with at least 2 elements
        // The length of the first element equals the position of that symbol
        $symbolElems = GeneralUtils::splitElements($haystack, $needle, $skipFromChars, $skipUntilChars);
        if (count($symbolElems) >= 2) {
            return strlen($symbolElems[0]);
        }
        // Edge case: If the string finishes with the symbol, then the array count of splitting the elements will be 1
        if (substr($haystack, -1*strlen($needle)) == $needle) {
            return strlen($haystack)-strlen($needle);
        }

        return false;
    }

    public static function findLastSymbolPosition(string $haystack, string $needle, $skipFromChars = '', $skipUntilChars = '')
    {
        // Edge case: If the string finishes with the symbol, then the array count of splitting the elements will be 1
        if (substr($haystack, -1*strlen($needle)) == $needle) {
            return strlen($haystack)-strlen($needle);
        }
        // Split on that searching element: If it appears within the string, it will produce an array with at least 2 elements
        // The length of the string minus the length of the last element element equals the position of that symbol
        $symbolElems = GeneralUtils::splitElements($haystack, $needle, $skipFromChars, $skipUntilChars);
        $symbolElemCount = count($symbolElems);
        if ($symbolElemCount >= 2) {
            return strlen($haystack)-(strlen($symbolElems[$symbolElemCount-1])+strlen($needle));
        }
        // Edge case: If the string starts with the symbol, then the array count of splitting the elements will be 1
        if (substr($haystack, 0, strlen($needle)) == $needle) {
            return 0;
        }

        return false;
    }
}
