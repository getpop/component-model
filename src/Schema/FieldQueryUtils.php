<?php
namespace PoP\ComponentModel\Schema;

use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

class FieldQueryUtils
{
    public static function isAnyFieldArgumentValueAField(array $fieldArgValues): bool
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $isOrContainsAField = array_map(
            function($fieldArgValue) use($fieldQueryInterpreter) {
                // Check if it has the representation of an array as a string. If so, convert to array
                $fieldArgValue = $fieldQueryInterpreter->maybeConvertFieldArgumentArrayValueFromStringToArray($fieldArgValue);
                // Either the value is a field, or it is an array of fields
                if (is_array($fieldArgValue)) {
                    return self::isAnyFieldArgumentValueAField((array)$fieldArgValue);
                }
                return $fieldQueryInterpreter->isFieldArgumentValueAField($fieldArgValue);
            },
            $fieldArgValues
        );
        return (in_array(true, $isOrContainsAField));
    }
}
