<?php
namespace PoP\ComponentModel\Schema;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

trait DirectiveValidatorTrait
{
    protected function dissectAndValidateDirectiveForResultItem($fieldResolver, $resultItem, string $directive, array &$dbErrors, array &$dbWarnings): array
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        list(
            $validDirective,
            $directiveName,
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
        }
        return [
            $validDirective,
            $directiveName,
            $directiveArgs,
        ];
    }
}
