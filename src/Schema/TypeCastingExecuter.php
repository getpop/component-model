<?php
namespace PoP\ComponentModel\Schema;

use PoP\ComponentModel\Error;
use PoP\Translation\Facades\TranslationAPIFacade;

class TypeCastingExecuter implements TypeCastingExecuterInterface
{
    /**
     * Cast the value to the indicated type, or return null or Error (with a message) if it fails
     *
     * @param string $type
     * @param string $value
     * @return void
     */
    public function cast(string $type, string $value)
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        switch ($type) {
            // case SchemaDefinition::TYPE_MIXED:
            // case SchemaDefinition::TYPE_ID:
            // case SchemaDefinition::TYPE_ARRAY:
            // case SchemaDefinition::TYPE_OBJECT:
            // case SchemaDefinition::TYPE_URL:
            // case SchemaDefinition::TYPE_EMAIL:
            // case SchemaDefinition::TYPE_IP:
            // case SchemaDefinition::TYPE_ENUM:
            // case SchemaDefinition::TYPE_STRING:
            //     return $value;
            case SchemaDefinition::TYPE_DATE:
                // Validate that the format is 'Y-m-d'
                // Taken from https://stackoverflow.com/a/13194398
                $dt = \DateTime::createFromFormat("Y-m-d", $value);
                if ($dt === false || array_sum($dt::getLastErrors())) {
                    return new Error('date-cast', sprintf(
                        $translationAPI->__('Date format must be \'%s\''),
                        'Y-m-d'
                    ));
                }
                return $value;
            case SchemaDefinition::TYPE_INT:
                return \CastToType::_int($value);
            case SchemaDefinition::TYPE_FLOAT:
                return \CastToType::_float($value);
            case SchemaDefinition::TYPE_BOOL:
                return \CastToType::_bool($value);
            case SchemaDefinition::TYPE_TIME:
                $converted = strtotime($value);
                if ($converted === false) {
                    return null;
                }
                return $converted;
        }
        return $value;
    }
}
