<?php
namespace PoP\ComponentModel\Schema;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

trait DirectiveValidatorTrait
{
    protected function validateDirectiveForResultItem($fieldResolver, $resultItem, string $directive, array &$dbErrors, array &$dbWarnings): array
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        list(
            $directiveArgs,
            $nestedDBErrors,
            $nestedDBWarnings
        ) = $fieldQueryInterpreter->extractFieldArgumentsForResultItem($fieldResolver, $resultItem, $directive);
        if ($nestedDBWarnings || $nestedDBErrors) {
            foreach ($nestedDBErrors as $id => $fieldOutputKeyErrorMessages) {
                $dbErrors[$id] = array_merge(
                    $dbErrors[$id] ?? [],
                    $fieldOutputKeyErrorMessages
                );
            }
            foreach ($nestedDBWarnings as $id => $fieldOutputKeyWarningMessages) {
                $dbWarnings[$id] = array_merge(
                    $dbWarnings[$id] ?? [],
                    $fieldOutputKeyWarningMessages
                );
            }
            // If there's an error, those args will be removed. Then, re-create the fieldDirective to pass it to the function below
            $directiveName = $fieldQueryInterpreter->getFieldDirectiveName($directive);
            return [
                $fieldQueryInterpreter->getFieldDirective($directiveName, $directiveArgs),
                $directiveArgs
            ];
        }
        return [
            $directive,
            $directiveArgs,
        ];
    }
}
