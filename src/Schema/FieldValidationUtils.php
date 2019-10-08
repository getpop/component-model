<?php
namespace PoP\ComponentModel\Schema;
use PoP\Translation\Facades\TranslationAPIFacade;

class FieldValidationUtils
{
    public static function validateNotMissingFieldArguments($fieldResolver, $fieldArgumentProperties, string $fieldName, array $fieldArgs = []): ?string
    {
        $missing = [];
        foreach ($fieldArgumentProperties as $fieldArgumentProperty) {
            if (!array_key_exists($fieldArgumentProperty, $fieldArgs)) {
                $missing[] = $fieldArgumentProperty;
            }
        }
        if ($missing) {
            $translationAPI = TranslationAPIFacade::getInstance();
            return count($missing) == 1 ?
                sprintf(
                    $translationAPI->__('Argument \'%s\' cannot be empty, so field \'%s\' has been ignored', 'pop-component-model'),
                    $missing[0],
                    $fieldName
                ) :
                sprintf(
                    $translationAPI->__('Arguments \'%s\' cannot be empty, so field \'%s\' has been ignored', 'pop-component-model'),
                    implode($translationAPI->__('\', \''), $missing),
                    $fieldName
                );
        }
        return null;
    }
}
