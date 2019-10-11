<?php
namespace PoP\ComponentModel\Schema;
use PoP\Translation\Contracts\TranslationAPIInterface;
use PoP\ComponentModel\GeneralUtils;

class FieldQueryInterpreter implements FieldQueryInterpreterInterface
{
    // Cache the output from functions
    private $fieldNamesCache = [];
    private $fieldArgsCache = [];
    private $extractedFieldArgumentsCache = [];
    private $extractedFieldArgumentWarningsCache = [];
    private $fieldArgumentNameTypesCache = [];
    private $fieldAliasesCache = [];
    private $fieldDirectivesCache = [];
    private $directivesCache = [];
    private $extractedFieldDirectivesCache = [];
    private $fieldOutputKeysCache = [];

    // Cache vars to take from the request
    private $variablesFromRequestCache;

    // Services
    private $translationAPI;
    private $errorMessageStore;
    private $typeCastingExecuter;

    public function __construct(
        TranslationAPIInterface $translationAPI,
        ErrorMessageStoreInterface $errorMessageStore,
        TypeCastingExecuterInterface $typeCastingExecuter
    ) {
        $this->translationAPI = $translationAPI;
        $this->errorMessageStore = $errorMessageStore;
        $this->typeCastingExecuter = $typeCastingExecuter;
    }

    public function getFieldName(string $field): string
    {
        if (!isset($this->fieldNamesCache[$field])) {
            $this->fieldNamesCache[$field] = $this->doGetFieldName($field);
        }
        return $this->fieldNamesCache[$field];
    }

    protected function doGetFieldName(string $field): string
    {
        // Successively search for the position of some edge symbol
        // Everything before "(" (for the fieldArgs)
        list($pos) = QueryHelpers::listFieldArgsSymbolPositions($field);
        // Everything before "@" (for the alias)
        if ($pos === false) {
            $pos = QueryHelpers::findFieldAliasSymbolPosition($field);
        }
        // Everything before "<" (for the field directive)
        if ($pos === false) {
            list($pos) = QueryHelpers::listFieldDirectivesSymbolPositions($field);
        }
        // If the field name is missing, show an error
        if ($pos === 0) {
            $this->errorMessageStore->addQueryError(sprintf(
                $this->translationAPI->__('Name in \'%s\' is missing', 'pop-component-model'),
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

    protected function getVariablesFromRequest(): array
    {
        if (is_null($this->variablesFromRequestCache)) {
            $this->variablesFromRequestCache = $this->doGetVariablesFromRequest();
        }
        return $this->variablesFromRequestCache;
    }

    protected function doGetVariablesFromRequest(): array
    {
        return array_merge(
            $_REQUEST,
            $_REQUEST['variables'] ?? []
        );
    }

    public function getFieldArgs(string $field): ?string
    {
        if (!isset($this->fieldArgsCache[$field])) {
            $this->fieldArgsCache[$field] = $this->doGetFieldArgs($field);
        }
        return $this->fieldArgsCache[$field];
    }

    protected function doGetFieldArgs(string $field): ?string
    {
        // We check that the format is "$fieldName($prop1;$prop2;...;$propN)"
        // or also with [] at the end: "$fieldName($prop1;$prop2;...;$propN)[somename]"
        list(
            $fieldArgsOpeningSymbolPos,
            $fieldArgsClosingSymbolPos
        ) = QueryHelpers::listFieldArgsSymbolPositions($field);

        // If there are no "(" and ")" then there are no field args
        if ($fieldArgsClosingSymbolPos === false && $fieldArgsOpeningSymbolPos === false) {
            return null;
        }
        // If there is only one of them, it's a query error, so discard the query bit
        if (($fieldArgsClosingSymbolPos === false && $fieldArgsOpeningSymbolPos !== false) || ($fieldArgsClosingSymbolPos !== false && $fieldArgsOpeningSymbolPos === false)) {
            $this->errorMessageStore->addQueryError(sprintf(
                $this->translationAPI->__('Arguments \'%s\' must start with symbol \'%s\' and end with symbol \'%s\'', 'pop-component-model'),
                $field,
                QuerySyntax::SYMBOL_FIELDARGS_OPENING,
                QuerySyntax::SYMBOL_FIELDARGS_CLOSING
            ));
            return null;
        }

        // We have field args. Extract them, including the brackets
        return substr($field, $fieldArgsOpeningSymbolPos, $fieldArgsClosingSymbolPos+strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING)-$fieldArgsOpeningSymbolPos);
    }

    public function extractFieldArguments($fieldResolver, string $field, ?array &$schemaWarnings = null): array
    {
        if (!isset($this->extractedFieldArgumentsCache[get_class($fieldResolver)][$field])) {
            $fieldSchemaWarnings = [];
            $this->extractedFieldArgumentsCache[get_class($fieldResolver)][$field] = $this->doExtractFieldArguments($fieldResolver, $field, $fieldSchemaWarnings);
            // Also cache the schemaWarnings
            if (!is_null($schemaWarnings)) {
                $this->extractedFieldArgumentWarningsCache[get_class($fieldResolver)][$field] = $fieldSchemaWarnings;
            }
        }
        // Integrate the schemaWarnings too
        if (!is_null($schemaWarnings)) {
            $schemaWarnings = array_merge(
                $schemaWarnings,
                $this->extractedFieldArgumentWarningsCache[get_class($fieldResolver)][$field]
            );
        }
        return $this->extractedFieldArgumentsCache[get_class($fieldResolver)][$field];
    }

    protected function doExtractFieldArguments($fieldResolver, string $field, ?array &$schemaWarnings = null): array
    {
        $fieldArgs = [];
        // Extract the args from the string into an array
        $fieldArgsStr = $this->getFieldArgs($field);
        // Remove the opening and closing brackets
        $fieldArgsStr = substr($fieldArgsStr, strlen(QuerySyntax::SYMBOL_FIELDARGS_OPENING), strlen($fieldArgsStr)-strlen(QuerySyntax::SYMBOL_FIELDARGS_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING));
        // Remove the white spaces before and after
        if ($fieldArgsStr = trim($fieldArgsStr)) {
            // Iterate all the elements, and extract them into the array
            if ($fieldArgElems = GeneralUtils::splitElements($fieldArgsStr, QuerySyntax::SYMBOL_FIELDARGS_ARGSEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING)) {
                // Important: provide the $fieldName instead of the $field to avoid an infinite loop
                $fieldName = $this->getFieldName($field);
                $orderedFieldArgNamesEnabled = $fieldResolver->enableOrderedFieldDocumentationArgs($fieldName);
                if ($orderedFieldArgNamesEnabled) {
                    $orderedFieldArgNames = array_keys($this->getFieldArgumentNameTypes($fieldResolver, $field));
                }
                for ($i=0; $i<count($fieldArgElems); $i++) {
                    $fieldArg = $fieldArgElems[$i];
                    // Either one of 2 formats are accepted:
                    // 1. The key:value pair
                    // 2. Only the value, and extract the key from the schema definition (if enabled for that field)
                    $separatorPos = QueryUtils::findFirstSymbolPosition($fieldArg, QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
                    if ($separatorPos === false) {
                        $fieldArgValue = $fieldArg;
                        if (!$orderedFieldArgNamesEnabled || !isset($orderedFieldArgNames[$i])) {
                            // Throw an error, if $schemaWarnings is provided (no need when extracting args for the resultItem, only for the schema)
                            if (!is_null($schemaWarnings)) {
                                $errorMessage = $orderedFieldArgNamesEnabled ?
                                    $this->translationAPI->__('documentation for this argument in the schema definition has not been defined, hence it can\'t be deduced from there', 'pop-component-model') :
                                    $this->translationAPI->__('retrieving this information from the schema definition is disabled for the corresponding “fieldResolver”', 'pop-component-model');
                                $schemaWarnings[] = sprintf(
                                    $this->translationAPI->__('The argument on position number %s (with value \'%s\') has its name missing, and %s. Please define the query using the \'key%svalue\' format. This argument has been ignored', 'pop-component-model'),
                                    $i+1,
                                    $fieldArgValue,
                                    $errorMessage,
                                    QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR
                                );
                            }
                            // Ignore extracting this argument
                            continue;
                        }
                        $fieldArgName = $orderedFieldArgNames[$i];
                    } else {
                        $fieldArgName = trim(substr($fieldArg, 0, $separatorPos));
                        $fieldArgValue = trim(substr($fieldArg, $separatorPos + strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR)));
                    }
                    $fieldArgs[$fieldArgName] = $fieldArgValue;
                }
            }
        }

        return $fieldArgs;
    }

    protected function filterFieldArgs($fieldArgs): array
    {
        // If there was an error, the value will be NULL. In this case, remove it
        return array_filter($fieldArgs, function($elem) {
            // Remove only NULL values and Errors. Keep '', 0 and false
            return !is_null($elem) && !GeneralUtils::isError($elem);
        });
    }

    public function extractFieldArgumentsForSchema($fieldResolver, string $field, ?array $variables = null): array
    {
        $schemaErrors = [];
        $schemaWarnings = [];
        $schemaDeprecations = [];
        $fieldName = $this->getFieldName($field);
        if ($fieldArgs = $this->extractFieldArguments($fieldResolver, $field, $schemaWarnings)) {
            foreach ($fieldArgs as $fieldArgName => $fieldArgValue) {
                $fieldArgValue = $this->maybeConvertFieldArgumentValue($fieldArgValue, $variables);
                $fieldArgs[$fieldArgName] = $fieldArgValue;
                // Validate it
                if ($maybeErrors = $this->resolveFieldArgumentValueErrorDescriptionsForSchema($fieldResolver, $fieldArgValue)) {
                    $schemaErrors = array_merge(
                        $schemaErrors,
                        $maybeErrors
                    );
                    // Because it's an error, set the value to null, so it will be filtered out
                    $fieldArgs[$fieldArgName] = null;
                }
                // Find warnings and deprecations
                if ($maybeWarnings = $this->resolveFieldArgumentValueWarningsForSchema($fieldResolver, $fieldArgValue)) {
                    $schemaWarnings = array_merge(
                        $schemaWarnings,
                        $maybeWarnings
                    );
                }
                if ($maybeDeprecations = $this->resolveFieldArgumentValueDeprecationsForSchema($fieldResolver, $fieldArgValue)) {
                    $schemaDeprecations = array_merge(
                        $schemaDeprecations,
                        $maybeDeprecations
                    );
                }
            }
            $fieldArgs = $this->filterFieldArgs($fieldArgs);
            // Cast the values to their appropriate type. If casting fails, the value returns as null
            $fieldArgs = $this->castAndValidateFieldArgumentsForSchema($fieldResolver, $field, $fieldArgs, $schemaWarnings);
        }
        // If there's an error, those args will be removed. Then, re-create the fieldDirective to pass it to the function below
        if ($schemaErrors) {
            $validField = null;
        } elseif ($schemaWarnings) {
            // Re-create the field, eliminating the fieldArgs that failed
            $validField = $this->getField(
                $fieldName,
                $fieldArgs,
                $this->getFieldAlias($field),
                $this->getDirectives($field)
            );
        } else {
            $validField = $field;
        }
        return [
            $validField,
            $fieldName,
            $fieldArgs,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations,
        ];
    }

    public function extractFieldArgumentsForResultItem($fieldResolver, $resultItem, string $field, ?array $variables = null): array
    {
        $dbErrors = $dbWarnings = [];
        $fieldArgs = $this->extractFieldArguments($fieldResolver, $field);
        $fieldOutputKey = $this->getFieldOutputKey($field);
        $id = $fieldResolver->getId($resultItem);
        foreach ($fieldArgs as $fieldArgName => $fieldArgValue) {
            $fieldArgValue = $this->maybeResolveFieldArgumentValueForResultItem($fieldResolver, $resultItem, $fieldArgValue, $variables);
            // Validate it
            if (\PoP\ComponentModel\GeneralUtils::isError($fieldArgValue)) {
                $error = $fieldArgValue;
                if ($errorData = $error->getErrorData()) {
                    $errorOutputKey = $errorData['fieldName'];
                }
                $errorOutputKey = $errorOutputKey ?? $fieldOutputKey;
                $dbErrors[(string)$id][$errorOutputKey][] = $error->getErrorMessage();
                $fieldArgs[$fieldArgName] = null;
                continue;
            }
            $fieldArgs[$fieldArgName] = $fieldArgValue;
        }
        $fieldArgs = $this->filterFieldArgs($fieldArgs);
        // Cast the values to their appropriate type. If casting fails, the value returns as null
        $resultItemDBWarnings = [];
        $fieldArgs = $this->castAndValidateFieldArgumentsForResultItem($fieldResolver, $field, $fieldArgs, $resultItemDBWarnings);
        foreach ($resultItemDBWarnings as $warning) {
            $dbWarnings[(string)$id][$fieldOutputKey][] = $warning;
        }
        return [
            $fieldArgs,
            $dbErrors,
            $dbWarnings
        ];
    }

    protected function castFieldArguments($fieldResolver, string $field, array $fieldArgs, array &$failedCastingFieldArgErrorMessages, bool $forSchema): array
    {
        // Get the field argument types, to know to what type it will cast the value
        if ($fieldArgNameTypes = $this->getFieldArgumentNameTypes($fieldResolver, $field)) {
            // Cast all argument values
            foreach ($fieldArgs as $fieldArgName => $fieldArgValue) {
                // Maybe cast the value to the appropriate type. Eg: from string to boolean
                if ($fieldArgType = $fieldArgNameTypes[$fieldArgName]) {
                    // There are 2 possibilities for casting:
                    // 1. $forSchema = true: Cast all items except fields (eg: has-comments())
                    // 2. $forSchema = false: Should be cast only fields, however by now we can't tell which are fields and which are not, since fields have already been resolved to their value. Hence, cast everything (fieldArgValues that failed at the schema level will not be provided in the input array, so won't be validated twice)
                    // Otherwise, simply add the fieldArgValue directly, it will be eventually casted by the other function
                    if (
                        ($forSchema && !$this->isFieldArgumentValueAField($fieldArgValue)) ||
                        !$forSchema
                    ) {
                        $fieldArgValue = $this->typeCastingExecuter->cast($fieldArgType, $fieldArgValue);
                        // If the response is an error, extract the error message and set value to null
                        if (GeneralUtils::isError($fieldArgValue)) {
                            $error = $fieldArgValue;
                            $failedCastingFieldArgErrorMessages[$fieldArgName] = $error->getErrorMessage();
                            $fieldArgs[$fieldArgName] = null;
                            continue;
                        }
                    }
                    $fieldArgs[$fieldArgName] = $fieldArgValue;
                }
            }
        }
        return $fieldArgs;
    }

    protected function castFieldArgumentsForSchema($fieldResolver, string $field, array $fieldArgs, array &$failedCastingFieldArgErrorMessages): array
    {
        return $this->castFieldArguments($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages, true);
    }

    protected function castFieldArgumentsForResultItem($fieldResolver, string $field, array $fieldArgs, array &$failedCastingFieldArgErrorMessages): array
    {
        return $this->castFieldArguments($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages, false);
    }

    protected function getFieldArgumentNameTypes($fieldResolver, string $field): array
    {
        if (!isset($this->fieldArgumentNameTypesCache[get_class($fieldResolver)][$field])) {
            $this->fieldArgumentNameTypesCache[get_class($fieldResolver)][$field] = $this->doGetFieldArgumentNameTypes($fieldResolver, $field);
        }
        return $this->fieldArgumentNameTypesCache[get_class($fieldResolver)][$field];
    }

    protected function doGetFieldArgumentNameTypes($fieldResolver, string $field): array
    {
        // Get the field argument types, to know to what type it will cast the value
        $fieldArgNameTypes = [];
        // Important: we must query by $fieldName and not $field or it enters an infinite loop
        $fieldName = $this->getFieldName($field);
        if ($fieldDocumentationArgs = $fieldResolver->getFieldDocumentationArgs($fieldName)) {
            foreach ($fieldDocumentationArgs as $fieldDocumentationArg) {
                $fieldArgNameTypes[$fieldDocumentationArg['name']] = $fieldDocumentationArg['type'];
            }
        }
        return $fieldArgNameTypes;
    }

    protected function castAndValidateFieldArgumentsForSchema($fieldResolver, string $field, array $fieldArgs, array &$schemaWarnings): array
    {
        $failedCastingFieldArgErrorMessages = [];
        $castedFieldArgs = $this->castFieldArgumentsForSchema($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages);
        return $this->castAndValidateFieldArguments($fieldResolver, $castedFieldArgs, $failedCastingFieldArgErrorMessages, $field, $fieldArgs, $schemaWarnings);
    }

    protected function castAndValidateFieldArgumentsForResultItem($fieldResolver, string $field, array $fieldArgs, array &$dbWarnings): array
    {
        $failedCastingFieldArgErrorMessages = [];
        $castedFieldArgs = $this->castFieldArgumentsForResultItem($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages);
        return $this->castAndValidateFieldArguments($fieldResolver, $castedFieldArgs, $failedCastingFieldArgErrorMessages, $field, $fieldArgs, $dbWarnings);
    }

    protected function castAndValidateFieldArguments($fieldResolver, array $castedFieldArgs, array &$failedCastingFieldArgErrorMessages, string $field, array $fieldArgs, array &$schemaWarnings): array
    {
        // If any casting can't be done, show an error
        if ($failedCastingFieldArgs = array_filter($castedFieldArgs, function($fieldArgValue) {
            return is_null($fieldArgValue);
        })) {
            $fieldName = $this->getFieldName($field);
            $fieldArgNameTypes = $this->getFieldArgumentNameTypes($fieldResolver, $field);
            foreach (array_keys($failedCastingFieldArgs) as $failedCastingFieldArgName) {
                // If it is Error, also show the error message
                if ($fieldArgErrorMessage = $failedCastingFieldArgErrorMessages[$failedCastingFieldArgName]) {
                    $errorMessage = sprintf(
                        $this->translationAPI->__('For field \'%s\', casting value \'%s\' for argument \'%s\' to type \'%s\' failed: %s. It has been ignored', 'pop-component-model'),
                        $fieldName,
                        $fieldArgs[$failedCastingFieldArgName],
                        $failedCastingFieldArgName,
                        $fieldArgNameTypes[$failedCastingFieldArgName],
                        $fieldArgErrorMessage
                    );
                } else {
                    $errorMessage = sprintf(
                        $this->translationAPI->__('For field \'%s\', casting value \'%s\' for argument \'%s\' to type \'%s\' failed, so it has been ignored', 'pop-component-model'),
                        $fieldName,
                        $fieldArgs[$failedCastingFieldArgName],
                        $failedCastingFieldArgName,
                        $fieldArgNameTypes[$failedCastingFieldArgName]
                    );
                }
                $schemaWarnings[] = $errorMessage;
            }
            return $this->filterFieldArgs($castedFieldArgs);
        }
        return $castedFieldArgs;
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
    protected function maybeConvertFieldArgumentValue($fieldArgValue, array $variables = null)
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
            if ($fieldArgValue = $this->maybeConvertFieldArgumentVariableValue($fieldArgValue, $variables)) {
                // Then convert to arrays
                return $this->maybeConvertFieldArgumentArrayValue($fieldArgValue, $variables);
            }
        }

        return $fieldArgValue;
    }

    protected function maybeConvertFieldArgumentVariableValue($fieldArgValue, array $variables = null)
    {
        // If it starts with "$", it is a variable. Then, retrieve the actual value from the request
        if (substr($fieldArgValue, 0, strlen(QuerySyntax::SYMBOL_VARIABLE_PREFIX)) == QuerySyntax::SYMBOL_VARIABLE_PREFIX) {
            // Variables: allow to pass a field argument "key:$input", and then resolve it as ?variable[input]=value
            // Expected input is similar to GraphQL: https://graphql.org/learn/queries/#variables
            // If not passed the variables parameter, use $_REQUEST["variables"] by default
            $variables = $variables ?? $this->getVariablesFromRequest();
            $variableName = substr($fieldArgValue, strlen(QuerySyntax::SYMBOL_VARIABLE_PREFIX));
            if (isset($variables[$variableName])) {
                return $variables[$variableName];
            }
            // If the variable is not set, then show the error under entry "variableErrors"
            $this->errorMessageStore->addQueryError(sprintf(
                $this->translationAPI->__('Variable \'%s\' is undefined', 'pop-component-model'),
                $variableName
            ));
            return null;
        }

        return $fieldArgValue;
    }

    protected function maybeConvertFieldArgumentArrayValue($fieldArgValue, array $variables = null)
    {
        // If surrounded by [...], it is an array
        if (substr($fieldArgValue, 0, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING && substr($fieldArgValue, -1*strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING) {
            $arrayValue = substr($fieldArgValue, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING), strlen($fieldArgValue)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING));
            // Elements are split by ";"
            $arrayValueElems = GeneralUtils::splitElements($arrayValue, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
            // Resolve each element the same way
            return $this->filterFieldArgs(array_map(function($arrayValueElem) use($variables) {
                return $this->maybeConvertFieldArgumentValue($arrayValueElem, $variables);
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
    protected function maybeResolveFieldArgumentValueForResultItem($fieldResolver, $resultItem, $fieldArgValue, array $variables = null)
    {
        // Do a conversion first.
        $convertedValue = $this->maybeConvertFieldArgumentValue($fieldArgValue, $variables);

        // If it is an array, apply this function on all elements
        if (is_array($convertedValue)) {
            return array_map(function($convertedValueElem) use($fieldResolver, $resultItem, $variables) {
                return $this->maybeResolveFieldArgumentValueForResultItem($fieldResolver, $resultItem, $convertedValueElem, $variables);
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

    protected function resolveFieldArgumentValueErrorDescriptionsForSchema($fieldResolver, $fieldArgValue, array $variables = null): ?array
    {
        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return GeneralUtils::arrayFlatten(array_filter(array_map(function($fieldArgValueElem) use($fieldResolver, $variables) {
                return $this->resolveFieldArgumentValueErrorDescriptionsForSchema($fieldResolver, $fieldArgValueElem, $variables);
            }, $fieldArgValue)));
        }

        // If the result fieldArgValue is a string (i.e. not numeric), and it has brackets (...),
        // then it is a field. Validate it and resolve it
        if (!empty($fieldArgValue) && is_string($fieldArgValue) && !is_numeric($fieldArgValue)) {

            // If it has the fieldArg brackets, then it's a field!
            list(
                $fieldArgsOpeningSymbolPos,
                $fieldArgsClosingSymbolPos
            ) = QueryHelpers::listFieldArgsSymbolPositions((string)$fieldArgValue);

            // If there are no "(" and ")" then it's simply a string
            if ($fieldArgsClosingSymbolPos === false && $fieldArgsOpeningSymbolPos === false) {
                return null;
            }
            // If there is only one of them, it's a query error, so discard the query bit
            $fieldArgValue = (string)$fieldArgValue;
            if (($fieldArgsClosingSymbolPos === false && $fieldArgsOpeningSymbolPos !== false) || ($fieldArgsClosingSymbolPos !== false && $fieldArgsOpeningSymbolPos === false)) {
                return [
                    sprintf(
                        $this->translationAPI->__('Arguments in field \'%s\' must start with symbol \'%s\' and end with symbol \'%s\', so they have been ignored', 'pop-component-model'),
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
                        $this->translationAPI->__('Field name is missing in \'%s\', so it has been ignored', 'pop-component-model'),
                        $fieldArgValue
                    ),
                ];
            }
            if ($fieldArgsClosingSymbolPos !== strlen($fieldArgValue)-1) {
                return [
                    sprintf(
                        $this->translationAPI->__('Field \'%s\' must end with argument symbol \'%s\', so it has been ignored', 'pop-component-model'),
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

    protected function resolveFieldArgumentValueWarningsForSchema($fieldResolver, $fieldArgValue, array $variables = null): ?array
    {
        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return GeneralUtils::arrayFlatten(array_filter(array_map(function($fieldArgValueElem) use($fieldResolver, $variables) {
                return $this->resolveFieldArgumentValueWarningsForSchema($fieldResolver, $fieldArgValueElem, $variables);
            }, $fieldArgValue)));
        }

        // If the result fieldArgValue is a field, then validate it and resolve it
        if ($this->isFieldArgumentValueAField($fieldArgValue)) {
            return $fieldResolver->getFieldDocumentationWarningDescriptions($fieldArgValue);
        }

        return null;
    }

    protected function resolveFieldArgumentValueDeprecationsForSchema($fieldResolver, $fieldArgValue, array $variables = null): ?array
    {
        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return GeneralUtils::arrayFlatten(array_filter(array_map(function($fieldArgValueElem) use($fieldResolver, $variables) {
                return $this->resolveFieldArgumentValueDeprecationsForSchema($fieldResolver, $fieldArgValueElem, $variables);
            }, $fieldArgValue)));
        }

        // If the result fieldArgValue is a field, then validate it and resolve it
        if ($this->isFieldArgumentValueAField($fieldArgValue)) {
            return $fieldResolver->getFieldDocumentationDeprecationDescriptions($fieldArgValue);
        }

        return null;
    }

    public function isFieldArgumentValueAField($fieldArgValue): bool
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

    public function getFieldAlias(string $field): ?string
    {
        if (!isset($this->fieldAliasesCache[$field])) {
            $this->fieldAliasesCache[$field] = $this->doGetFieldAlias($field);
        }
        return $this->fieldAliasesCache[$field];
    }

    protected function doGetFieldAlias(string $field): ?string
    {
        $aliasPrefixSymbolPos = QueryHelpers::findFieldAliasSymbolPosition($field);
        if ($aliasPrefixSymbolPos !== false) {
            if ($aliasPrefixSymbolPos === 0) {
                // Only there is the alias, nothing to alias to
                $this->errorMessageStore->addQueryError(sprintf(
                    $this->translationAPI->__('The field to be aliased in \'%s\' is missing', 'pop-component-model'),
                    $field
                ));
                return null;
            } elseif ($aliasPrefixSymbolPos === strlen($field)-1) {
                // Only the "@" was added, but the alias is missing
                $this->errorMessageStore->addQueryError(sprintf(
                    $this->translationAPI->__('Alias in \'%s\' is missing', 'pop-component-model'),
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

    public function getFieldDirectives(string $field): ?string
    {
        if (!isset($this->fieldDirectivesCache[$field])) {
            $this->fieldDirectivesCache[$field] = $this->doGetFieldDirectives($field);
        }
        return $this->fieldDirectivesCache[$field];
    }

    protected function doGetFieldDirectives(string $field): ?string
    {
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
            $this->errorMessageStore->addQueryError(sprintf(
                $this->translationAPI->__('Directive \'%s\' must start with symbol \'%s\' and end with symbol \'%s\'', 'pop-component-model'),
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

    public function getDirectives(string $field): array
    {
        if (!isset($this->directivesCache[$field])) {
            $this->directivesCache[$field] = $this->doGetDirectives($field);
        }
        return $this->directivesCache[$field];
    }

    protected function doGetDirectives(string $field): array
    {
        $fieldDirectives = $this->getFieldDirectives($field);
        if (is_null($fieldDirectives)) {
            return [];
        }
        return $this->extractFieldDirectives($fieldDirectives);
    }

    public function extractFieldDirectives(string $fieldDirectives): array
    {
        if (!isset($this->extractedFieldDirectivesCache[$fieldDirectives])) {
            $this->extractedFieldDirectivesCache[$fieldDirectives] = $this->doExtractFieldDirectives($fieldDirectives);
        }
        return $this->extractedFieldDirectivesCache[$fieldDirectives];
    }

    protected function doExtractFieldDirectives(string $fieldDirectives): array
    {
        if (!$fieldDirectives) {
            return [];
        }
        return array_map(
            [$this, 'listFieldDirective'],
            GeneralUtils::splitElements($fieldDirectives, QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_BOOKMARK_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_BOOKMARK_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING)
        );
    }

    public function composeFieldDirectives(array $fieldDirectives): string
    {
        return implode(QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, $fieldDirectives);
    }

    public function convertDirectiveToFieldDirective(array $fieldDirective): string
    {
        $directiveArgs = $this->getDirectiveArgs($fieldDirective) ?? '';
        return $this->getDirectiveName($fieldDirective).$directiveArgs;
    }

    public function listFieldDirective(string $fieldDirective): array
    {
        // Each item is an array of 2 elements: 0 => name, 1 => args
        return [
            $this->getFieldName($fieldDirective),
            $this->getFieldArgs($fieldDirective),
        ];
    }

    public function getFieldDirectiveName(string $fieldDirective): string
    {
        return $this->getFieldName($fieldDirective);
    }

    public function getFieldDirectiveArgs(string $fieldDirective): ?string
    {
        return $this->getFieldArgs($fieldDirective);
    }

    public function getFieldDirective(string $directiveName, array $directiveArgs = []): string
    {
        return $this->getField($directiveName, $directiveArgs);
    }

    public function getDirectiveName(array $directive): string
    {
        return $directive[0];
    }

    public function getDirectiveArgs(array $directive): ?string
    {
        return $directive[1];
    }

    public function getFieldOutputKey(string $field): string
    {
        if (!isset($this->fieldOutputKeysCache[$field])) {
            $this->fieldOutputKeysCache[$field] = $this->doGetFieldOutputKey($field);
        }
        return $this->fieldOutputKeysCache[$field];
    }

    protected function doGetFieldOutputKey(string $field): string
    {
        // If there is an alias, use this to represent the field
        if ($fieldAlias = $this->getFieldAlias($field)) {
            return $fieldAlias;
        }
        // Otherwise, use fieldName+fieldArgs (hence, $field minus the directive)
        $fieldDirectiveOpeningSymbolElems = GeneralUtils::splitElements($field, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING, QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
        return $fieldDirectiveOpeningSymbolElems[0];
    }

    public function getField(string $fieldName, array $fieldArgs = [], string $fieldAlias = null, array $fieldDirectives = []): string
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
                    return $this->getFieldDirectiveAsString($fieldDirective);
                },
                $fieldDirectives
            ) : '');
    }

    public function getFieldDirectiveAsString(array $fieldDirectives): string
    {
        // The directive has the same structure as the field, so reuse the function
        return QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING.implode(QuerySyntax::SYMBOL_QUERYFIELDS_SEPARATOR, array_map(function($fieldDirective) {
            return $this->getField($fieldDirective[0], $fieldDirective[1]);
        }, $fieldDirectives)).QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING;
    }
}
