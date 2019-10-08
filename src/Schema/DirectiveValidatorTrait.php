<?php
namespace PoP\ComponentModel\Schema;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

trait DirectiveValidatorTrait
{
    protected function validateDirectiveForSchema($fieldResolver, string $directive, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        // First validate schema (eg of error in schema: ?fields=posts<include(if:this-field-doesnt-exist())>)
        list(
            $directiveArgs,
            $directiveSchemaErrors,
            $directiveSchemaWarnings,
            $directiveSchemaDeprecations
        ) = $fieldQueryInterpreter->extractFieldArgumentsForSchema($fieldResolver, $directive);

        // If there were errors, save them and remove the corresponding args from the directive
        if ($directiveSchemaErrors || $directiveSchemaWarnings || $directiveSchemaDeprecations) {
            $directiveOutputKey = FieldQueryInterpreterFacade::getInstance()->getFieldOutputKey($directive);
            foreach ($directiveSchemaErrors as $error) {
                $schemaErrors[$directiveOutputKey][] = $error;
            }
            foreach ($directiveSchemaWarnings as $error) {
                $schemaWarnings[$directiveOutputKey][] = $error;
            }
            foreach ($directiveSchemaDeprecations as $error) {
                $schemaDeprecations[$directiveOutputKey][] = $error;
            }
            // If there's an error, those args will be removed. Then, re-create the fieldDirective to pass it to the function below
            $directiveName = $fieldQueryInterpreter->getFieldDirectiveName($directive);
            return [
                $fieldQueryInterpreter->getFieldDirective($directiveName, $directiveArgs),
                $directiveArgs,
            ];
        }
        return [
            $directive,
            $directiveArgs,
        ];
    }

    protected function validateDirectiveForResultItem($fieldResolver, $resultItem, string $directive, array &$dbErrors): array
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        list(
            $directiveArgs,
            $nestedDBErrors
        ) = $fieldQueryInterpreter->extractFieldArgumentsForResultItem($fieldResolver, $resultItem, $directive);
        if ($nestedDBErrors) {
            foreach ($nestedDBErrors as $id => $fieldOutputKeyErrorMessages) {
                $dbErrors[$id] = array_merge(
                    $dbErrors[$id] ?? [],
                    $fieldOutputKeyErrorMessages
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
