<?php
namespace PoP\ComponentModel\Schema;

class SchemaHelpers
{
    public static function getMissingFieldArgs(array $argumentProperties, array $fieldArgs): array {
        return array_filter(
            $argumentProperties,
            function($argumentProperty) use($fieldArgs) {
                return !array_key_exists($argumentProperty, $fieldArgs);
            }
        );
    }
}
