<?php
namespace PoP\ComponentModel\Schema;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\GeneralUtils;

class FieldQueryInterpreter
{
    protected static $fieldNames = [];
    protected static $variablesFromRequest;
    protected static $fieldArgs = [];
    protected static $extractedFieldArguments = [];
    protected static $fieldArgumentNameTypes = [];
    protected static $fieldAliases = [];
    protected static $fieldDirectives = [];
    protected static $directives = [];
    protected static $extractedFieldDirectives = [];
    protected static $fieldOutputKeys = [];
    protected static $expandedRelationalProperties = [];
    protected static $fragmentsFromRequest;

    protected static $errorMessageStore;

    public static function getFieldName(string $field): string
    {
        if (!isset(self::$fieldNames[$field])) {
            self::$fieldNames[$field] = self::doGetFieldName($field);
        }
        return self::$fieldNames[$field];
    }

    protected static function doGetFieldName(string $field): string
    {
        // Successively search for the position of some edge symbol
        // Everything before "(" (for the fieldArgs)
        list($pos) = QueryHelpers::listFieldArgsSymbolPositions($field);
        // Everything before "@" (for the alias)
        if ($pos === false) {
            $pos  = QueryHelpers::findFieldAliasSymbolPosition($field);
        }
        // Everything before "<" (for the field directive)
        if ($pos === false) {
            list($pos) = QueryHelpers::listFieldDirectivesSymbolPositions($field);
        }
        // If the field name is missing, show an error
        if ($pos === 0) {
            $translationAPI = TranslationAPIFacade::getInstance();
            ErrorMessageStoreFacade::getInstance()->addQueryError(sprintf(
                $translationAPI->__('Name in \'%s\' is missing', 'pop-component-model'),
                $field
            ));
            return '';
        }
        // Extract the query until the found position
        if ($pos !== false) {
            return strtolower(substr($field, 0, $pos));
        }
        // No fieldArgs, no alias => The field is the fieldName
        return strtolower($field);
    }

    protected static function getVariablesFromRequest(): array
    {
        if (is_null(self::$variablesFromRequest)) {
            self::$variablesFromRequest = self::doGetVariablesFromRequest();
        }
        return self::$variablesFromRequest;
    }

    protected static function doGetVariablesFromRequest(): array
    {
        return array_merge(
            $_REQUEST,
            $_REQUEST['variables'] ?? []
        );
    }

    public static function getFieldArgs(string $field): ?string
    {
        if (!isset(self::$fieldArgs[$field])) {
            self::$fieldArgs[$field] = self::doGetFieldArgs($field);
        }
        return self::$fieldArgs[$field];
    }

    protected static function doGetFieldArgs(string $field): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        // We check that the format is "$fieldName($prop1;$prop2;...;$propN)"
        // or also with [] at the end: "$fieldName($prop1;$prop2;...;$propN)[somename]"
        list(
            $fieldArgsOpeningSymbolPos,
            $fieldArgsClosingSymbolPos
        )  = QueryHelpers::listFieldArgsSymbolPositions($field);

        // If there are no "(" and ")" then there are no field args
        if ($fieldArgsClosingSymbolPos === false && $fieldArgsOpeningSymbolPos === false) {
            return null;
        }
        // If there is only one of them, it's a query error, so discard the query bit
        if (($fieldArgsClosingSymbolPos === false && $fieldArgsOpeningSymbolPos !== false) || ($fieldArgsClosingSymbolPos !== false && $fieldArgsOpeningSymbolPos === false)) {
            ErrorMessageStoreFacade::getInstance()->addQueryError(sprintf(
                $translationAPI->__('Arguments \'%s\' must start with symbol \'%s\' and end with symbol \'%s\'', 'pop-component-model'),
                $field,
                QuerySyntax::SYMBOL_FIELDARGS_OPENING,
                QuerySyntax::SYMBOL_FIELDARGS_CLOSING
            ));
            return null;
        }

        // We have field args. Extract them, including the brackets
        return substr($field, $fieldArgsOpeningSymbolPos, $fieldArgsClosingSymbolPos+strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING)-$fieldArgsOpeningSymbolPos);
    }

    protected static function extractFieldArguments(string $field): array
    {
        if (!isset(self::$extractedFieldArguments[$field])) {
            self::$extractedFieldArguments[$field] = self::doExtractFieldArguments($field);
        }
        return self::$extractedFieldArguments[$field];
    }

    protected static function doExtractFieldArguments(string $field): array
    {
        $fieldArgs = [];
        // Extract the args from the string into an array
        $fieldArgsStr = self::getFieldArgs($field);
        // Remove the opening and closing brackets
        $fieldArgsStr = substr($fieldArgsStr, strlen(QuerySyntax::SYMBOL_FIELDARGS_OPENING), strlen($fieldArgsStr)-strlen(QuerySyntax::SYMBOL_FIELDARGS_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING));
        // Remove the white spaces before and after
        if ($fieldArgsStr = trim($fieldArgsStr)) {
            // Iterate all the elements, and extract them into the array
            foreach (GeneralUtils::splitElements($fieldArgsStr, QuerySyntax::SYMBOL_FIELDARGS_ARGSEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING]) as $fieldArg) {
                $fieldArgParts = GeneralUtils::splitElements($fieldArg, QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_CLOSING]);
                $fieldArgName = trim($fieldArgParts[0]);
                $fieldArgValue = trim($fieldArgParts[1]);
                $fieldArgs[$fieldArgName] = $fieldArgValue;
            }
        }

        return $fieldArgs;
    }

    protected static function filterFieldArgs($fieldArgs): array
    {
        // If there was an error, the value will be NULL. In this case, remove it
        return array_filter($fieldArgs, function($elem) {
            // Remove only NULL values and Errors. Keep '', 0 and false
            return !is_null($elem) && !GeneralUtils::isError($elem);
        });
    }

    public static function extractFieldArgumentsForResultItem($fieldResolver, $resultItem, string $field, ?array $variables = null): array
    {
        $dbErrors = [];
        $fieldArgs = self::extractFieldArguments($field);
        $fieldOutputKey = self::getFieldOutputKey($field);
        $id = $fieldResolver->getId($resultItem);
        foreach ($fieldArgs as $fieldArgName => $fieldArgValue) {
            $fieldArgValue = self::maybeResolveFieldArgumentValueForResultItem($fieldResolver, $resultItem, $fieldArgValue, $variables);
            // Validate it
            if (\PoP\ComponentModel\GeneralUtils::isError($fieldArgValue)) {
                $error = $fieldArgValue;
                $dbErrors[(string)$id][$fieldOutputKey][] = $error->getErrorMessage();
                $fieldArgs[$fieldArgName] = null;
            } else {
                $fieldArgs[$fieldArgName] = $fieldArgValue;
            }
        }
        $fieldArgs = self::filterFieldArgs($fieldArgs);
        // Cast the values to their appropriate type. No need to do anything about the errors
        $failedCastingFieldArgErrorMessages = [];
        $fieldArgs = self::castFieldArguments($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages);
        return [
            $fieldArgs,
            $dbErrors
        ];
    }

    protected static function castFieldArguments($fieldResolver, string $field, array $fieldArgs, array &$failedCastingFieldArgErrorMessages): array
    {
        // Get the field argument types, to know to what type it will cast the value
        if ($fieldArgNameTypes = self::getFieldArgumentNameTypes($fieldResolver, $field)) {
            // Cast all argument values
            foreach ($fieldArgs as $fieldArgName => $fieldArgValue) {
                // Maybe cast the value to the appropriate type. Eg: from string to boolean
                if ($fieldArgType = $fieldArgNameTypes[$fieldArgName]) {
                    $fieldArgValue = TypeCastingExecuter::cast($fieldArgType, $fieldArgValue);
                    // If the response is an error, extract the error message and set value to null
                    if (GeneralUtils::isError($fieldArgValue)) {
                        $error = $fieldArgValue;
                        $failedCastingFieldArgErrorMessages[$fieldArgName] = $error->getErrorMessage();
                        $fieldArgs[$fieldArgName] = null;
                        continue;
                    }
                    $fieldArgs[$fieldArgName] = $fieldArgValue;
                }
            }
        }
        return $fieldArgs;
    }

    protected static function getFieldArgumentNameTypes($fieldResolver, string $field): array
    {
        if (!isset(self::$fieldArgumentNameTypes[get_class($fieldResolver)][$field])) {
            self::$fieldArgumentNameTypes[get_class($fieldResolver)][$field] = self::doGetFieldArgumentNameTypes($fieldResolver, $field);
        }
        return self::$fieldArgumentNameTypes[get_class($fieldResolver)][$field];
    }

    protected static function doGetFieldArgumentNameTypes($fieldResolver, string $field): array
    {
        // Get the field argument types, to know to what type it will cast the value
        $fieldArgNameTypes = [];
        // Important: we must query by $fieldName and not $field or it enters an infinite loop
        $fieldName = self::getFieldName($field);
        if ($fieldDocumentationArgs = $fieldResolver->getFieldDocumentationArgs($fieldName)) {
            foreach ($fieldDocumentationArgs as $fieldDocumentationArg) {
                $fieldArgNameTypes[$fieldDocumentationArg['name']] = $fieldDocumentationArg['type'];
            }
        }
        return $fieldArgNameTypes;
    }

    protected static function castAndValidateFieldArguments($fieldResolver, string $field, array $fieldArgs, array &$schemaWarnings): array
    {
        $failedCastingFieldArgErrorMessages = [];
        $castedFieldArgs = self::castFieldArguments($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages);
        // If any casting can't be done, show an error
        if ($failedCastingFieldArgs = array_filter($castedFieldArgs, function($fieldArgValue) {
            return is_null($fieldArgValue);
        })) {
            $translationAPI = TranslationAPIFacade::getInstance();
            $fieldArgNameTypes = self::getFieldArgumentNameTypes($fieldResolver, $field);
            foreach ($failedCastingFieldArgs as $fieldArgName => $fieldArgValue) {
                // If it is Error, also show the error message
                if ($fieldArgErrorMessage = $failedCastingFieldArgErrorMessages[$fieldArgName]) {
                    $errorMessage = sprintf(
                        $translationAPI->__('Casting value \'%s\' for argument \'%s\' to type \'%s\' failed: %s. It has been ignored', 'pop-component-model'),
                        $fieldArgs[$fieldArgName],
                        $fieldArgName,
                        $fieldArgNameTypes[$fieldArgName],
                        $fieldArgErrorMessage
                    );
                } else {
                    $errorMessage = sprintf(
                        $translationAPI->__('Casting value \'%s\' for argument \'%s\' to type \'%s\' failed, so it has been ignored', 'pop-component-model'),
                        $fieldArgs[$fieldArgName],
                        $fieldArgName,
                        $fieldArgNameTypes[$fieldArgName]
                    );
                }
                $schemaWarnings[] = $errorMessage;
            }
            return self::filterFieldArgs($castedFieldArgs);
        }
        return $castedFieldArgs;
    }

    public static function extractFieldArgumentsForSchema($fieldResolver, string $field, ?array $variables = null): array
    {
        $schemaErrors = [];
        $schemaWarnings = [];
        $schemaDeprecations = [];
        if ($fieldArgs = self::extractFieldArguments($field)) {
            foreach ($fieldArgs as $fieldArgName => $fieldArgValue) {
                $fieldArgValue = self::maybeConvertFieldArgumentValue($fieldArgValue, $variables);
                // Validate it
                if ($maybeErrors = self::resolveFieldArgumentValueErrorDescriptionsForSchema($fieldResolver, $fieldArgValue)) {
                    $schemaErrors = array_merge(
                        $schemaErrors,
                        $maybeErrors
                    );
                    $fieldArgs[$fieldArgName] = null;
                    continue;
                }
                // Find warnings and deprecations
                if ($maybeWarnings = self::resolveFieldArgumentValueWarningsForSchema($fieldResolver, $fieldArgValue)) {
                    $schemaWarnings = array_merge(
                        $schemaWarnings,
                        $maybeWarnings
                    );
                }
                if ($maybeDeprecations = self::resolveFieldArgumentValueDeprecationsForSchema($fieldResolver, $fieldArgValue)) {
                    $schemaDeprecations = array_merge(
                        $schemaDeprecations,
                        $maybeDeprecations
                    );
                }
                $fieldArgs[$fieldArgName] = $fieldArgValue;
            }
            $fieldArgs = self::filterFieldArgs($fieldArgs);
            // Cast the values to their appropriate type. If casting fails, the value returns as null
            $fieldArgs = self::castAndValidateFieldArguments($fieldResolver, $field, $fieldArgs, $schemaWarnings);
        }
        return [
            $fieldArgs,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations,
        ];
    }

    /**
     * The value may be:
     * - A variable, if it starts with "$"
     * - An array, if it is surrounded with brackets and split with commas ([..., ..., ...])
     * - A number/string/field otherwise
     *
     * @param [type] $fieldResolver
     * @param [type] $fieldArgValue
     * @param [type] $variables
     * @return mixed
     */
    protected static function maybeConvertFieldArgumentValue($fieldArgValue, array $variables = null)
    {
        // Remove the white spaces before and after
        if ($fieldArgValue = trim($fieldArgValue)) {
            // Special case: when wrapping a string between quotes (eg: to avoid it being treated as a field, such as: posts(searchfor:"image(vertical)")),
            // the quotes are converted, from:
            // "value"
            // to:
            // "\"value\""
            // Transform back. Keep the quotes so that the string is still not converted to a field
            $fieldArgValue = stripcslashes($fieldArgValue);

            // Chain functions. At any moment, if any of them throws an error, the result will be null so don't process anymore
            // First replace all variables
            if ($fieldArgValue = self::maybeConvertFieldArgumentVariableValue($fieldArgValue, $variables)) {
                // Then convert to arrays
                return self::maybeConvertFieldArgumentArrayValue($fieldArgValue, $variables);
            }
        }

        return $fieldArgValue;
    }

    protected static function maybeConvertFieldArgumentVariableValue($fieldArgValue, array $variables = null)
    {
        // If it starts with "$", it is a variable. Then, retrieve the actual value from the request
        if (substr($fieldArgValue, 0, strlen(QuerySyntax::SYMBOL_VARIABLE_PREFIX)) == QuerySyntax::SYMBOL_VARIABLE_PREFIX) {
            // Variables: allow to pass a field argument "key:$input", and then resolve it as ?variable[input]=value
            // Expected input is similar to GraphQL: https://graphql.org/learn/queries/#variables
            // If not passed the variables parameter, use $_REQUEST["variables"] by default
            $variables = $variables ?? self::getVariablesFromRequest();
            $variableName = substr($fieldArgValue, strlen(QuerySyntax::SYMBOL_VARIABLE_PREFIX));
            if (isset($variables[$variableName])) {
                return $variables[$variableName];
            }
            // If the variable is not set, then show the error under entry "variableErrors"
            $translationAPI = TranslationAPIFacade::getInstance();
            ErrorMessageStoreFacade::getInstance()->addQueryError(sprintf(
                $translationAPI->__('Variable \'%s\' is undefined', 'pop-component-model'),
                $variableName
            ));
            return null;
        }

        return $fieldArgValue;
    }

    protected static function maybeConvertFieldArgumentArrayValue($fieldArgValue, array $variables = null)
    {
        // If surrounded by [...], it is an array
        if (substr($fieldArgValue, 0, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING && substr($fieldArgValue, -1*strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING) {
            $arrayValue = substr($fieldArgValue, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING), strlen($fieldArgValue)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING));
            // Elements are split by ";"
            $arrayValueElems = GeneralUtils::splitElements($arrayValue, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING]);
            // Resolve each element the same way
            return self::filterFieldArgs(array_map(function($arrayValueElem) use($variables) {
                return self::maybeConvertFieldArgumentValue($arrayValueElem, $variables);
            }, $arrayValueElems));
        }

        return $fieldArgValue;
    }

    /**
     * The value may be:
     * - A variable, if it starts with "$"
     * - A string, if it is surrounded with double quotes ("...")
     * - An array, if it is surrounded with brackets and split with commas ([..., ..., ...])
     * - A number
     * - A field
     *
     * @param [type] $fieldResolver
     * @param [type] $fieldArgValue
     * @param [type] $variables
     * @return mixed
     */
    protected static function maybeResolveFieldArgumentValueForResultItem($fieldResolver, $resultItem, $fieldArgValue, array $variables = null)
    {
        // Do a static conversion first.
        $convertedValue = self::maybeConvertFieldArgumentValue($fieldArgValue, $variables);

        // If it is an array, apply this function on all elements
        if (is_array($convertedValue)) {
            return array_map(function($convertedValueElem) use($fieldResolver, $resultItem, $variables) {
                return self::maybeResolveFieldArgumentValueForResultItem($fieldResolver, $resultItem, $convertedValueElem, $variables);
            }, $convertedValue);
        }

        // Convert field, remove quotes from strings
        if (!empty($convertedValue) && is_string($convertedValue)) {
            // If the result convertedValue is a string (i.e. not numeric), and it has brackets (...),
            // then it is a field. Validate it and resolve it
            if (
                substr($convertedValue, -1*strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING)) == QuerySyntax::SYMBOL_FIELDARGS_CLOSING &&
                // Please notice: if position is 0 (i.e. for a string "(something)") then it's not a field, since the fieldName is missing
                // Then it's ok asking for strpos: either `false` or `0` must both fail
                strpos($convertedValue, QuerySyntax::SYMBOL_FIELDARGS_OPENING)
            ) {
                return $fieldResolver->resolveValue($resultItem, $convertedValue);
            }
            // If it has quotes at the beginning and end, it's a string. Remove them
            if (
                substr($convertedValue, 0, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING &&
                substr($convertedValue, -1*strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING
            ) {
                return substr($convertedValue, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING), strlen($convertedValue)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING));
            }
        }

        return $convertedValue;
    }

    protected static function resolveFieldArgumentValueErrorDescriptionsForSchema($fieldResolver, $fieldArgValue, array $variables = null): ?array
    {
        $translationAPI = TranslationAPIFacade::getInstance();

        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return GeneralUtils::arrayFlatten(array_filter(array_map(function($fieldArgValueElem) use($fieldResolver, $variables) {
                return self::resolveFieldArgumentValueErrorDescriptionsForSchema($fieldResolver, $fieldArgValueElem, $variables);
            }, $fieldArgValue)));
        }

        // If the result fieldArgValue is a string (i.e. not numeric), and it has brackets (...),
        // then it is a field. Validate it and resolve it
        if (!empty($fieldArgValue) && is_string($fieldArgValue) && !is_numeric($fieldArgValue)) {

            // If it has the fieldArg brackets, then it's a field!
            list(
                $fieldArgsOpeningSymbolPos,
                $fieldArgsClosingSymbolPos
            )  = QueryHelpers::listFieldArgsSymbolPositions((string)$fieldArgValue);

            // If there are no "(" and ")" then it's simply a string
            if ($fieldArgsClosingSymbolPos === false && $fieldArgsOpeningSymbolPos === false) {
                return null;
            }
            // If there is only one of them, it's a query error, so discard the query bit
            $fieldArgValue = (string)$fieldArgValue;
            if (($fieldArgsClosingSymbolPos === false && $fieldArgsOpeningSymbolPos !== false) || ($fieldArgsClosingSymbolPos !== false && $fieldArgsOpeningSymbolPos === false)) {
                return [
                    sprintf(
                        $translationAPI->__('Arguments in field \'%s\' must start with symbol \'%s\' and end with symbol \'%s\', so they have been ignored', 'pop-component-model'),
                        $fieldArgValue,
                        QuerySyntax::SYMBOL_FIELDARGS_OPENING,
                        QuerySyntax::SYMBOL_FIELDARGS_CLOSING
                    ),
                ];
            }

            // If the opening bracket is at the beginning, or the closing one is not at the end, it's an error
            if ($fieldArgsOpeningSymbolPos === 0) {
                return [
                    sprintf(
                        $translationAPI->__('Field name is missing in \'%s\', so it has been ignored', 'pop-component-model'),
                        $fieldArgValue
                    ),
                ];
            }
            if ($fieldArgsClosingSymbolPos !== strlen($fieldArgValue)-1) {
                return [
                    sprintf(
                        $translationAPI->__('Field \'%s\' must end with argument symbol \'%s\', so it has been ignored', 'pop-component-model'),
                        $fieldArgValue,
                        QuerySyntax::SYMBOL_FIELDARGS_CLOSING
                    ),
                ];
            }

            // If it reached here, it's a field! Validate it, or show an error
            return $fieldResolver->resolveSchemaValidationErrorDescriptions($fieldArgValue);
        }

        return null;
    }

    protected static function resolveFieldArgumentValueWarningsForSchema($fieldResolver, $fieldArgValue, array $variables = null): ?array
    {
        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return GeneralUtils::arrayFlatten(array_filter(array_map(function($fieldArgValueElem) use($fieldResolver, $variables) {
                return self::resolveFieldArgumentValueWarningsForSchema($fieldResolver, $fieldArgValueElem, $variables);
            }, $fieldArgValue)));
        }

        // If the result fieldArgValue is a field, then validate it and resolve it
        if (self::isFieldArgumentValueAField($fieldResolver, $fieldArgValue)) {
            return $fieldResolver->getFieldDocumentationWarningDescriptions($fieldArgValue);
        }

        return null;
    }

    protected static function resolveFieldArgumentValueDeprecationsForSchema($fieldResolver, $fieldArgValue, array $variables = null): ?array
    {
        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return GeneralUtils::arrayFlatten(array_filter(array_map(function($fieldArgValueElem) use($fieldResolver, $variables) {
                return self::resolveFieldArgumentValueDeprecationsForSchema($fieldResolver, $fieldArgValueElem, $variables);
            }, $fieldArgValue)));
        }

        // If the result fieldArgValue is a field, then validate it and resolve it
        if (self::isFieldArgumentValueAField($fieldResolver, $fieldArgValue)) {
            return $fieldResolver->getFieldDocumentationDeprecationDescriptions($fieldArgValue);
        }

        return null;
    }

    protected static function isFieldArgumentValueAField($fieldResolver, $fieldArgValue): bool
    {
        // If the result fieldArgValue is a string (i.e. not numeric), and it has brackets (...),
        // then it is a field
        return
            !empty($fieldArgValue) &&
            is_string($fieldArgValue) &&
            substr($fieldArgValue, -1*strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING)) == QuerySyntax::SYMBOL_FIELDARGS_CLOSING &&
            // Please notice: if position is 0 (i.e. for a string "(something)") then it's not a field, since the fieldName is missing
            // Then it's ok asking for strpos: either `false` or `0` must both fail
            strpos($fieldArgValue, QuerySyntax::SYMBOL_FIELDARGS_OPENING);
    }

    public static function getFieldAlias(string $field): ?string
    {
        if (!isset(self::$fieldAliases[$field])) {
            self::$fieldAliases[$field] = self::doGetFieldAlias($field);
        }
        return self::$fieldAliases[$field];
    }

    protected static function doGetFieldAlias(string $field): ?string
    {
        $aliasPrefixSymbolPos = QueryHelpers::findFieldAliasSymbolPosition($field);
        if ($aliasPrefixSymbolPos !== false) {
            $translationAPI = TranslationAPIFacade::getInstance();
            if ($aliasPrefixSymbolPos === 0) {
                // Only there is the alias, nothing to alias to
                ErrorMessageStoreFacade::getInstance()->addQueryError(sprintf(
                    $translationAPI->__('The field to be aliased in \'%s\' is missing', 'pop-component-model'),
                    $field
                ));
                return null;
            } elseif ($aliasPrefixSymbolPos === strlen($field)-1) {
                // Only the "@" was added, but the alias is missing
                ErrorMessageStoreFacade::getInstance()->addQueryError(sprintf(
                    $translationAPI->__('Alias in \'%s\' is missing', 'pop-component-model'),
                    $field
                ));
                return null;
            }

            // Extract the alias, without the "@" symbol
            $alias = substr($field, $aliasPrefixSymbolPos+strlen(QuerySyntax::SYMBOL_FIELDALIAS_PREFIX));

            // If there is a field directive (after the alias), remove it
            list(
                $fieldDirectivesOpeningSymbolPos
            ) = QueryHelpers::listFieldDirectivesSymbolPositions($alias);
            if ($fieldDirectivesOpeningSymbolPos !== false) {
                $alias = substr($alias, 0, $fieldDirectivesOpeningSymbolPos);
            }
            return $alias;
        }
        return null;
    }

    public static function getFieldDirectives(string $field): ?string
    {
        if (!isset(self::$fieldDirectives[$field])) {
            self::$fieldDirectives[$field] = self::doGetFieldDirectives($field);
        }
        return self::$fieldDirectives[$field];
    }

    protected static function doGetFieldDirectives(string $field): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();

        list(
            $fieldDirectivesOpeningSymbolPos,
            $fieldDirectivesClosingSymbolPos
        ) = QueryHelpers::listFieldDirectivesSymbolPositions($field);

        // If there are no "<" and "." then there is no directive
        if ($fieldDirectivesClosingSymbolPos === false && $fieldDirectivesOpeningSymbolPos === false) {
            return null;
        }
        // If there is only one of them, it's a query error, so discard the query bit
        if (($fieldDirectivesClosingSymbolPos === false && $fieldDirectivesOpeningSymbolPos !== false) || ($fieldDirectivesClosingSymbolPos !== false && $fieldDirectivesOpeningSymbolPos === false)) {
            ErrorMessageStoreFacade::getInstance()->addQueryError(sprintf(
                $translationAPI->__('Directive \'%s\' must start with symbol \'%s\' and end with symbol \'%s\'', 'pop-component-model'),
                $field,
                QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING,
                QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING
            ));
            return null;
        }

        // We have a field directive. Extract it
        $fieldDirectiveOpeningSymbolStrPos = $fieldDirectivesOpeningSymbolPos+strlen(QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING);
        $fieldDirectiveClosingStrPos = $fieldDirectivesClosingSymbolPos - $fieldDirectiveOpeningSymbolStrPos;
        return substr($field, $fieldDirectiveOpeningSymbolStrPos, $fieldDirectiveClosingStrPos);
    }

    public static function getDirectives(string $field): array
    {
        if (!isset(self::$directives[$field])) {
            self::$directives[$field] = self::doGetDirectives($field);
        }
        return self::$directives[$field];
    }

    protected static function doGetDirectives(string $field): array
    {
        $fieldDirectives = self::getFieldDirectives($field);
        if (is_null($fieldDirectives)) {
            return [];
        }
        return self::extractFieldDirectives($fieldDirectives);
    }

    public static function extractFieldDirectives(string $fieldDirectives): array
    {
        if (!isset(self::$extractedFieldDirectives[$fieldDirectives])) {
            self::$extractedFieldDirectives[$fieldDirectives] = self::doExtractFieldDirectives($fieldDirectives);
        }
        return self::$extractedFieldDirectives[$fieldDirectives];
    }

    protected static function doExtractFieldDirectives(string $fieldDirectives): array
    {
        if (!$fieldDirectives) {
            return [];
        }
        return array_map(
            [self::class, 'listFieldDirective'],
            GeneralUtils::splitElements($fieldDirectives, QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING])
        );
    }

    public static function composeFieldDirectives(array $fieldDirectives): string
    {
        return implode(QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, $fieldDirectives);
    }

    public static function convertDirectiveToFieldDirective(array $fieldDirective): string
    {
        $directiveArgs = self::getDirectiveArgs($fieldDirective) ?? '';
        return self::getDirectiveName($fieldDirective).$directiveArgs;
    }

    public static function listFieldDirective(string $fieldDirective): array
    {
        // Each item is an array of 2 elements: 0 => name, 1 => args
        return [
            self::getFieldName($fieldDirective),
            self::getFieldArgs($fieldDirective),
        ];
    }

    public static function getFieldDirectiveName(string $fieldDirective): string
    {
        return self::getFieldName($fieldDirective);
    }

    public static function getFieldDirectiveArgs(string $fieldDirective): ?string
    {
        return self::getFieldArgs($fieldDirective);
    }

    public static function getFieldDirective(string $directiveName, array $directiveArgs = []): string
    {
        return self::getField($directiveName, $directiveArgs);
    }

    public static function getDirectiveName(array $directive): string
    {
        return $directive[0];
    }

    public static function getDirectiveArgs(array $directive): ?string
    {
        return $directive[1];
    }

    public static function getFieldOutputKey(string $field): string
    {
        if (!isset(self::$fieldOutputKeys[$field])) {
            self::$fieldOutputKeys[$field] = self::doGetFieldOutputKey($field);
        }
        return self::$fieldOutputKeys[$field];
    }

    protected static function doGetFieldOutputKey(string $field): string
    {
        // If there is an alias, use this to represent the field
        if ($fieldAlias = self::getFieldAlias($field)) {
            return $fieldAlias;
        }
        // Otherwise, use fieldName+fieldArgs (hence, $field minus the directive)
        $fieldDirectiveOpeningSymbolElems = GeneralUtils::splitElements($field, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING, QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDARGS_CLOSING);
        return $fieldDirectiveOpeningSymbolElems[0];
    }

    public static function getField(string $fieldName, array $fieldArgs = [], string $fieldAlias = null, array $fieldDirectives = []): string
    {
        $elems = [];
        foreach ($fieldArgs as $key => $value) {
            $elems[] = $key.QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR.$value;
        }
        return $fieldName.
            (!empty($elems) ? QuerySyntax::SYMBOL_FIELDARGS_OPENING.implode(QuerySyntax::SYMBOL_FIELDARGS_ARGSEPARATOR, $elems).QuerySyntax::SYMBOL_FIELDARGS_CLOSING : '').
            ($fieldAlias ? QuerySyntax::SYMBOL_FIELDALIAS_PREFIX.$fieldAlias : '').
            ($fieldDirectives ? array_map(
                function($fieldDirective) {
                    return self::getFieldDirectiveAsString($fieldDirective);
                },
                $fieldDirectives
            ) : '');
    }

    public static function getFieldDirectiveAsString(array $fieldDirectives): string
    {
        // The directive has the same structure as the field, so reuse the function
        return QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING.implode(QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, array_map(function($fieldDirective) {
            return self::getField($fieldDirective[0], $fieldDirective[1]);
        }, $fieldDirectives)).QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING;
    }

    protected static function expandRelationalProperties(string $dotNotation): string
    {
        if (!isset(self::$expandedRelationalProperties[$dotNotation])) {
            self::$expandedRelationalProperties[$dotNotation] = self::doExpandRelationalProperties($dotNotation);
        }
        return self::$expandedRelationalProperties[$dotNotation];
    }

    protected static function doExpandRelationalProperties(string $dotNotation): string
    {
        // Support a query combining relational and properties:
        // ?field=posts.id|title|author.id|name|posts.id|title|author.name
        // Transform it into:
        // ?field=posts.id|title,posts.author.id|name,posts.author.posts.id|title,posts.author.posts.author.name
        // Strategy: continuously search for "." appearing after "|", recreate their full path, and add them as new query sections (separated by ",")
        $expandedDotNotations = [];
        foreach (GeneralUtils::splitElements($dotNotation, QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING]) as $commafields) {
            $dotPos = strpos($commafields, QuerySyntax::SYMBOL_RELATIONALFIELDS_NEXTLEVEL);
            if ($dotPos !== false) {
                while ($dotPos !== false) {
                    // Position of the first "|". Everything before there is path + first property
                    // We must make sure the "|" is not inside "()", otherwise this would fail:
                    // /api/graphql/?fields=posts(order:title|asc).id|title
                    $pipeElements = GeneralUtils::splitElements($commafields, QuerySyntax::SYMBOL_FIELDPROPERTIES_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING]);
                    if (count($pipeElements) >= 2) {
                        $pipePos = \strlen($pipeElements[0]);
                        // Make sure the dot is not inside "()". Otherwise this will not work:
                        // /api/graphql/?fields=posts(order:title|asc).id|date(format:Y.m.d)
                        $pipeRest = substr($commafields, 0, $pipePos);
                        $dotElements = GeneralUtils::splitElements($pipeRest, QuerySyntax::SYMBOL_RELATIONALFIELDS_NEXTLEVEL, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING]);
                        // Watch out case in which there is no previous sectionPath. Eg: fields=id|comments.id
                        if ($lastDotPos = strlen($pipeRest) - strlen($dotElements[count($dotElements)-1])) {
                            // The path to the properties
                            $sectionPath = substr($commafields, 0, $lastDotPos);
                            // Combination of properties and, possibly, further relational ElemCount
                            $sectionRest = substr($commafields, $lastDotPos);
                        } else {
                            $sectionPath = '';
                            $sectionRest = $commafields;
                        }
                        // If there is another "." after a "|", then it keeps going down the relational path to load other elements
                        $sectionRestPipePos = strpos($sectionRest, QuerySyntax::SYMBOL_FIELDPROPERTIES_SEPARATOR);
                        $sectionRestDotPos = strpos($sectionRest, QuerySyntax::SYMBOL_RELATIONALFIELDS_NEXTLEVEL);
                        if ($sectionRestPipePos !== false && $sectionRestDotPos !== false && $sectionRestDotPos > $sectionRestPipePos) {
                            // Extract the last property, from which further relational ElemCount are loaded, and create a new query section for it
                            // This is the subtring from the last ocurrence of "|" before the "." up to the "."
                            $lastPipePos = strrpos(
                                substr(
                                    $sectionRest,
                                    0,
                                    $sectionRestDotPos
                                ),
                                QuerySyntax::SYMBOL_FIELDPROPERTIES_SEPARATOR
                            );
                            // Extract the new "rest" of the query section
                            $querySectionRest = substr(
                                $sectionRest,
                                $lastPipePos+strlen(QuerySyntax::SYMBOL_FIELDPROPERTIES_SEPARATOR)
                            );
                            // Remove the relational property from the now only properties part
                            $sectionRest = substr(
                                $sectionRest,
                                0,
                                $lastPipePos
                            );
                            // Add these as 2 independent ElemCount to the query
                            $expandedDotNotations[] = $sectionPath.$sectionRest;
                            $commafields = $sectionPath.$querySectionRest;
                            // Keep iterating
                            $dotPos = strpos($commafields, QuerySyntax::SYMBOL_RELATIONALFIELDS_NEXTLEVEL);
                        } else {
                            // The element has no further relationships
                            $expandedDotNotations[] = $commafields;
                            // Break out from the cycle
                            break;
                        }
                    } else {
                        // The element has no further relationships
                        $expandedDotNotations[] = $commafields;
                        // Break out from the cycle
                        break;
                    }
                }
            } else {
                // The element has no relationships
                $expandedDotNotations[] = $commafields;
            }
        }

        // Recombine all the elements
        return implode(QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, $expandedDotNotations);
    }

    protected static function getFragment($fragmentName, array $fragments): ?string
    {
        // A fragment can itself contain fragments!
        if ($fragment = $fragments[$fragmentName]) {
            return self::replaceFragments($fragment, $fragments);
        }
        return null;
    }

    protected static function replaceFragments(string $commafields, array $fragments): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();

        // The fields are split by "."
        // Watch out: we need to ignore all instances of "(" and ")" which may happen inside the fieldArg values!
        // Eg: /api/?fields=posts(searchfor:this => ( and this => ) are part of the search too).id|title
        $dotfields = GeneralUtils::splitElements($commafields, QuerySyntax::SYMBOL_RELATIONALFIELDS_NEXTLEVEL, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING], true);

        // Replace all fragment placeholders with the actual fragments
        // Do this at the beginning, because the fragment may contain new leaves, which need be at the last level of the $dotfields array. So this array must be recalculated after replacing the fragments in
        // Iterate from right to left, because after replacing the fragment in, the length of $dotfields may increase
        // Right now only for the properties. For the path will be done immediately after
        $lastLevel = count($dotfields)-1;
        // Replace fragments for the properties, adding them to temporary variable $lastLevelProperties
        $pipefields = GeneralUtils::splitElements($dotfields[$lastLevel], QuerySyntax::SYMBOL_FIELDPROPERTIES_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING]);
        $lastPropertyNumber = count($pipefields)-1;
        $lastLevelProperties = [];
        for ($propertyNumber=0; $propertyNumber<=$lastPropertyNumber; $propertyNumber++) {
            // If it starts with "*", then it's a fragment
            if (substr($pipefields[$propertyNumber], 0, strlen(QuerySyntax::SYMBOL_FRAGMENT_PREFIX)) == QuerySyntax::SYMBOL_FRAGMENT_PREFIX) {
                // Replace with the actual fragment
                $fragmentName = substr($pipefields[$propertyNumber], strlen(QuerySyntax::SYMBOL_FRAGMENT_PREFIX));
                if ($fragment = self::getFragment($fragmentName, $fragments)) {
                    $lastLevelProperties[] = $fragment;
                } else {
                    ErrorMessageStoreFacade::getInstance()->addQueryError(sprintf(
                        $translationAPI->__('Fragment \'%s\' is undefined, so it has been ignored', 'pop-component-model'),
                        $fragmentName
                    ));
                }
            } else {
                $lastLevelProperties[] = $pipefields[$propertyNumber];
            }
        }
        // Assign variable $lastLevelProperties (which contains the replaced fragments) back to the last level of $dotfields
        $dotfields[$lastLevel] = implode(QuerySyntax::SYMBOL_FIELDPROPERTIES_SEPARATOR, $lastLevelProperties);

        // Now replace fragments for properties
        for ($pathLevel=$lastLevel-1; $pathLevel>=0; $pathLevel--) {
            // If it starts with "*", then it's a fragment
            if (substr($dotfields[$pathLevel], 0, strlen(QuerySyntax::SYMBOL_FRAGMENT_PREFIX)) == QuerySyntax::SYMBOL_FRAGMENT_PREFIX) {
                // Replace with the actual fragment
                $fragmentName = substr($dotfields[$pathLevel], strlen(QuerySyntax::SYMBOL_FRAGMENT_PREFIX));
                if ($fragment = self::getFragment($fragmentName, $fragments)) {
                    $fragmentDotfields = GeneralUtils::splitElements($fragment, QuerySyntax::SYMBOL_RELATIONALFIELDS_NEXTLEVEL, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING], true);
                    array_splice($dotfields, $pathLevel, 1, $fragmentDotfields);
                } else {
                    ErrorMessageStoreFacade::getInstance()->addQueryError(sprintf(
                        $translationAPI->__('Fragment \'%s\' is undefined, so query section \'%s\' has been ignored', 'pop-component-model'),
                        $fragmentName,
                        $commafields
                    ));
                    // Remove whole query section
                    return null;
                }
            }
        }

        // If we reach here, there were no errors with any path level, so add element again on array
        return implode(QuerySyntax::SYMBOL_RELATIONALFIELDS_NEXTLEVEL, $dotfields);
    }

    protected static function validateProperty($property, $querySection = null)
    {
        $translationAPI = TranslationAPIFacade::getInstance();

        $errorMessageEnd = $querySection ?
            sprintf(
                $translationAPI->__('Query section \'%s\' has been ignored', 'pop-component-model'),
                $querySection
            ) :
            $translationAPI->__('The property has been ignored', 'pop-component-model');

        // --------------------------------------------------------
        // Validate correctness of query constituents: fieldArgs, bookmark, directive
        // --------------------------------------------------------
        // Field Args
        list(
            $fieldArgsOpeningSymbolPos,
            $fieldArgsClosingSymbolPos
        ) = QueryHelpers::listFieldArgsSymbolPositions($property);

        // If it has "(" from the very beginning, then there's no fieldName, it's an error
        if ($fieldArgsOpeningSymbolPos === 0) {
            return sprintf(
                $translationAPI->__('Property \'%s\' is missing the field name. %s', 'pop-component-model'),
                $property,
                $errorMessageEnd
            );
        }

        // If it has only "(" or ")" but not the other one, it's an error
        if (($fieldArgsClosingSymbolPos === false && $fieldArgsOpeningSymbolPos !== false) || ($fieldArgsClosingSymbolPos !== false && $fieldArgsOpeningSymbolPos === false)) {
            return sprintf(
                $translationAPI->__('Arguments \'%s\' must start with symbol \'%s\' and end with symbol \'%s\'. %s', 'pop-component-model'),
                $property,
                QuerySyntax::SYMBOL_FIELDARGS_OPENING,
                QuerySyntax::SYMBOL_FIELDARGS_CLOSING,
                $errorMessageEnd
            );
        }

        // Bookmarks
        list(
            $bookmarkOpeningSymbolPos,
            $bookmarkClosingSymbolPos
        ) = QueryHelpers::listFieldBookmarkSymbolPositions($property);

        // If it has "[" from the very beginning, then there's no fieldName, it's an error
        if ($bookmarkOpeningSymbolPos === 0) {
            return sprintf(
                $translationAPI->__('Property \'%s\' is missing the field name. %s', 'pop-component-model'),
                $property,
                $errorMessageEnd
            );
        }

        // If it has only "[" or "]" but not the other one, it's an error
        if (($bookmarkClosingSymbolPos === false && $bookmarkOpeningSymbolPos !== false) || ($bookmarkClosingSymbolPos !== false && $bookmarkOpeningSymbolPos === false)) {
            return sprintf(
                $translationAPI->__('Bookmark \'%s\' must start with symbol \'%s\' and end with symbol \'%s\'. %s', 'pop-component-model'),
                $property,
                QuerySyntax::SYMBOL_BOOKMARK_OPENING,
                QuerySyntax::SYMBOL_BOOKMARK_CLOSING,
                $errorMessageEnd
            );
        }

        // Field Directives
        list(
            $fieldDirectivesOpeningSymbolPos,
            $fieldDirectivesClosingSymbolPos
        ) = QueryHelpers::listFieldDirectivesSymbolPositions($property);

        // If it has "<" from the very beginning, then there's no fieldName, it's an error
        if ($fieldDirectivesOpeningSymbolPos === 0) {
            return sprintf(
                $translationAPI->__('Property \'%s\' is missing the field name. %s', 'pop-component-model'),
                $property,
                $errorMessageEnd
            );
        }

        // If it has only "[" or "]" but not the other one, it's an error
        if (($fieldDirectivesClosingSymbolPos === false && $fieldDirectivesOpeningSymbolPos !== false) || ($fieldDirectivesClosingSymbolPos !== false && $fieldDirectivesOpeningSymbolPos === false)) {
            return sprintf(
                $translationAPI->__('Directive \'%s\' must start with symbol \'%s\' and end with symbol \'%s\'. %s', 'pop-component-model'),
                $property,
                QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING,
                QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING,
                $errorMessageEnd
            );
        }

        // --------------------------------------------------------
        // Validate correctness of order of elements: ...(...)[...]<...>
        // (0. field name, 1. field args, 2. bookmarks, 3. field directives)
        // --------------------------------------------------------
        // After the ")", it must be either the end, "@", "[" or "<"
        if ($fieldArgsOpeningSymbolPos !== false) {
            if ($fieldArgsOpeningSymbolPos == 0) {
                return sprintf(
                    $translationAPI->__('Name is missing in property \'%s\'. %s', 'pop-component-model'),
                    $property,
                    $errorMessageEnd
                );
            }
        }

        // After the ")", it must be either the end, "@", "[" or "<"
        if ($fieldArgsClosingSymbolPos !== false) {
            $aliasSymbolPos = QueryHelpers::findFieldAliasSymbolPosition($property);
            $nextCharPos = $fieldArgsClosingSymbolPos+strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING);
            if (!(
                // It's in the last position
                ($fieldArgsClosingSymbolPos == strlen($property)-strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING)) ||
                // Next comes "["
                ($bookmarkOpeningSymbolPos !== false && $bookmarkOpeningSymbolPos == $nextCharPos) ||
                // Next comes "@"
                ($aliasSymbolPos !== false && $aliasSymbolPos == $nextCharPos) ||
                // Next comes "<"
                ($fieldDirectivesOpeningSymbolPos !== false && $fieldDirectivesOpeningSymbolPos == $nextCharPos)
            )) {
                return sprintf(
                    $translationAPI->__('After \'%s\', property \'%s\' must either end or be followed by \'%s\', \'%s\' or \'%s\'. %s', 'pop-component-model'),
                    QuerySyntax::SYMBOL_FIELDARGS_CLOSING,
                    $property,
                    QuerySyntax::SYMBOL_BOOKMARK_OPENING,
                    QuerySyntax::SYMBOL_FIELDALIAS_PREFIX,
                    QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING,
                    $errorMessageEnd
                );
            }
        }

        // After the "]", it must be either the end or "<"
        if ($bookmarkClosingSymbolPos !== false) {
            if (!(
                // It's in the last position
                ($bookmarkClosingSymbolPos == strlen($property)-strlen(QuerySyntax::SYMBOL_BOOKMARK_CLOSING)) ||
                // Next comes "["
                ($fieldDirectivesOpeningSymbolPos !== false && $fieldDirectivesOpeningSymbolPos == $bookmarkClosingSymbolPos+strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING))
            )) {
                return sprintf(
                    $translationAPI->__('After \'%s\', property \'%s\' must either end or be followed by \'%s\'. %s', 'pop-component-model'),
                    QuerySyntax::SYMBOL_BOOKMARK_CLOSING,
                    $property,
                    QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING,
                    $errorMessageEnd
                );
            }
        }

        // After the ">", it must be the end
        if ($fieldDirectivesClosingSymbolPos !== false) {
            if (!(
                // It's in the last position
                ($fieldDirectivesClosingSymbolPos == strlen($property)-strlen(QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING))
            )) {
                return sprintf(
                    $translationAPI->__('After \'%s\', property \'%s\' must end (there cannot be any extra character). %s', 'pop-component-model'),
                    QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING,
                    $property,
                    $errorMessageEnd
                );
            }
        }

        return [
            $fieldArgsOpeningSymbolPos,
            $fieldArgsClosingSymbolPos,
            $bookmarkOpeningSymbolPos,
            $bookmarkClosingSymbolPos,
            $fieldDirectivesOpeningSymbolPos,
            $fieldDirectivesClosingSymbolPos,
        ];
    }

    public static function convertAPIQueryFromStringToArray(string $dotNotation, ?array $fragments = null): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $fragments = $fragments ?? self::getFragmentsFromRequest();

        // If it is a string, split the ElemCount with ',', the inner ElemCount with '.', and the inner fields with '|'
        $fields = [];

        // Support a query combining relational and properties:
        // ?field=posts.id|title|author.id|name|posts.id|title|author.name
        // Transform it into:
        // ?field=posts.id|title,posts.author.id|name,posts.author.posts.id|title,posts.author.posts.author.name
        $dotNotation = self::expandRelationalProperties($dotNotation);

        // Replace all fragment placeholders with the actual fragments
        $replacedDotNotation = [];
        foreach (GeneralUtils::splitElements($dotNotation, QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING]) as $commafields) {
            if ($replacedCommaFields = self::replaceFragments($commafields, $fragments)) {
                $replacedDotNotation[] = $replacedCommaFields;
            }
        }
        if ($dotNotation = implode(QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, $replacedDotNotation)) {

            // After replacing the fragments, expand relational properties once again, since any such string could have been provided through a fragment
            // Eg: a fragment can contain strings such as "id|author.id"
            $dotNotation = self::expandRelationalProperties($dotNotation);

            // Initialize the pointer
            $pointer = &$fields;

            // Allow for bookmarks, similar to GraphQL: https://graphql.org/learn/queries/#bookmarks
            // The bookmark "prev" (under constant TOKEN_BOOKMARK) is a reserved one: it always refers to the previous query node
            $bookmarkPaths = [];

            // Split the ElemCount by ",". Use `splitElements` instead of `explode` so that the "," can also be inside the fieldArgs
            foreach (GeneralUtils::splitElements($dotNotation, QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING]) as $commafields) {

                // The fields are split by "."
                // Watch out: we need to ignore all instances of "(" and ")" which may happen inside the fieldArg values!
                // Eg: /api/?fields=posts(searchfor:this => ( and this => ) are part of the search too).id|title
                $dotfields = GeneralUtils::splitElements($commafields, QuerySyntax::SYMBOL_RELATIONALFIELDS_NEXTLEVEL, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING], true);

                // If there is a path to the node...
                if (count($dotfields) >= 2) {
                    // If surrounded by "[]", the first element references a bookmark from a previous iteration. If so, retrieve it
                    $firstPathLevel = $dotfields[0];
                    // Remove the fieldDirective, if it has one
                    if ($fieldDirectiveSplit = GeneralUtils::splitElements($firstPathLevel, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING, QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDARGS_CLOSING)) {
                        $firstPathLevel = $fieldDirectiveSplit[0];
                    }
                    if (
                        (substr($firstPathLevel, 0, strlen(QuerySyntax::SYMBOL_BOOKMARK_OPENING)) == QuerySyntax::SYMBOL_BOOKMARK_OPENING) &&
                        (substr($firstPathLevel, -1*strlen(QuerySyntax::SYMBOL_BOOKMARK_CLOSING)) == QuerySyntax::SYMBOL_BOOKMARK_CLOSING)
                    ) {
                        $bookmark = substr($firstPathLevel, strlen(QuerySyntax::SYMBOL_BOOKMARK_OPENING), strlen($firstPathLevel)-1-strlen(QuerySyntax::SYMBOL_BOOKMARK_CLOSING));
                        // If this bookmark was not set...
                        if (!isset($bookmarkPaths[$bookmark])) {
                            // Show an error and discard this element
                            $errorMessage = sprintf(
                                $translationAPI->__('Query path alias \'%s\' is undefined. Query section \'%s\' has been ignored', 'pop-component-model'),
                                $bookmark,
                                $commafields
                            );
                            ErrorMessageStoreFacade::getInstance()->addQueryError($errorMessage);
                            unset($bookmarkPaths[QuerySyntax::TOKEN_BOOKMARK_PREV]);
                            continue;
                        }
                        // Replace the first element with the bookmark path
                        array_shift($dotfields);
                        $dotfields = array_merge(
                            $bookmarkPaths[$bookmark],
                            $dotfields
                        );
                    }

                    // At every subpath, it can define a bookmark to that fragment by adding "[bookmarkName]" at its end
                    for ($pathLevel=0; $pathLevel<count($dotfields)-1; $pathLevel++) {

                        $errorMessageOrSymbolPositions = self::validateProperty(
                            $dotfields[$pathLevel],
                            $commafields
                        );
                        // If the validation is a string, then it's an error
                        if (is_string($errorMessageOrSymbolPositions)) {
                            $error = (string)$errorMessageOrSymbolPositions;
                            ErrorMessageStoreFacade::getInstance()->addQueryError($error);
                            unset($bookmarkPaths[QuerySyntax::TOKEN_BOOKMARK_PREV]);
                            // Exit 2 levels, so it doesn't process the whole query section, not just the property
                            continue 2;
                        }
                        // Otherwise, it is an array with all the symbol positions
                        $symbolPositions = (array)$errorMessageOrSymbolPositions;
                        list(
                            $fieldArgsOpeningSymbolPos,
                            $fieldArgsClosingSymbolPos,
                            $bookmarkOpeningSymbolPos,
                            $bookmarkClosingSymbolPos,
                            $fieldDirectivesOpeningSymbolPos,
                            $fieldDirectivesClosingSymbolPos,
                        ) = $symbolPositions;

                        // If it has both "[" and "]"...
                        if ($bookmarkClosingSymbolPos !== false && $bookmarkOpeningSymbolPos !== false) {
                            // Extract the bookmark
                            $startAliasPos = $bookmarkOpeningSymbolPos+strlen(QuerySyntax::SYMBOL_BOOKMARK_OPENING);
                            $bookmark = substr($dotfields[$pathLevel], $startAliasPos, $bookmarkClosingSymbolPos-$startAliasPos);

                            // If the bookmark starts with "@", it's also a property alias.
                            $alias = '';
                            if (substr($bookmark, 0, strlen(QuerySyntax::SYMBOL_FIELDALIAS_PREFIX)) == QuerySyntax::SYMBOL_FIELDALIAS_PREFIX) {
                                // Add the alias again to the pathLevel item, in the right format:
                                // Instead of fieldName[@alias] it is fieldName@alias
                                $alias = $bookmark;
                                $bookmark = substr($bookmark, strlen(QuerySyntax::SYMBOL_FIELDALIAS_PREFIX));
                            }

                            // Remove the bookmark from the path. Add the alias again, and keep the fieldDirective "<...>
                            $dotfields[$pathLevel] =
                                substr($dotfields[$pathLevel], 0, $bookmarkOpeningSymbolPos).
                                $alias.
                                (
                                    ($fieldDirectivesOpeningSymbolPos !== false) ?
                                        substr($dotfields[$pathLevel], $fieldDirectivesOpeningSymbolPos) :
                                        ''
                                );

                            // Recalculate the path (all the levels until the pathLevel), and store it to be used on a later iteration
                            $bookmarkPath = $dotfields;
                            array_splice($bookmarkPath, $pathLevel+1);
                            $bookmarkPaths[$bookmark] = $bookmarkPath;
                            // This works now:
                            // ?fields=posts(limit:3;search:template)[@posts].id|title,[posts].url
                            // Also support appending "@" before the bookmark for the aliases
                            // ?fields=posts(limit:3;search:template)[@posts].id|title,[@posts].url
                            if ($alias) {
                                $bookmarkPaths[$alias] = $bookmarkPath;
                            }
                        }
                    }

                    // Calculate the new "prev" bookmark path
                    $bookmarkPrevPath = $dotfields;
                    array_pop($bookmarkPrevPath);
                    $bookmarkPaths[QuerySyntax::TOKEN_BOOKMARK_PREV] = $bookmarkPrevPath;
                }

                // For each item, advance to the last level by following the "."
                for ($i = 0; $i < count($dotfields)-1; $i++) {
                    $pointer[$dotfields[$i]] = $pointer[$dotfields[$i]] ?? array();
                    $pointer = &$pointer[$dotfields[$i]];
                }

                // The last level can contain several fields, separated by "|"
                $pipefields = $dotfields[count($dotfields)-1];
                // Use `splitElements` instead of `explode` so that the "|" can also be inside the fieldArgs (eg: order:title|asc)
                foreach (GeneralUtils::splitElements($pipefields, QuerySyntax::SYMBOL_FIELDPROPERTIES_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING]) as $pipefield) {
                    $errorMessageOrSymbolPositions = self::validateProperty(
                        $pipefield
                    );
                    // If the validation is a string, then it's an error
                    if (is_string($errorMessageOrSymbolPositions)) {
                        $error = (string)$errorMessageOrSymbolPositions;
                        ErrorMessageStoreFacade::getInstance()->addQueryError($error);
                        // Exit 1 levels, so it ignores only this property but keeps processing the others
                        continue;
                    }
                    $pointer[] = $pipefield;
                }
                $pointer = &$fields;
            }
        }

        return $fields;
    }

    protected static function getFragmentsFromRequest(): array
    {
        if (is_null(self::$fragmentsFromRequest)) {
            self::$fragmentsFromRequest = self::doGetFragmentsFromRequest();
        }
        return self::$fragmentsFromRequest;
    }

    protected static function doGetFragmentsFromRequest(): array
    {
        // Each fragment is provided through $_REQUEST[fragments][fragmentName] or directly $_REQUEST[fragmentName]
        return array_merge(
            $_REQUEST,
            $_REQUEST['fragments'] ?? []
        );
    }
}
