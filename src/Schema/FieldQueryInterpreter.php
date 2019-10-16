<?php
namespace PoP\ComponentModel\Schema;
use PoP\ComponentModel\GeneralUtils;
use PoP\FieldQuery\Query\FieldQueryUtils;
use PoP\FieldQuery\Query\QueryUtils;
use PoP\Translation\Contracts\TranslationAPIInterface;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\QueryParsing\Parsers\QueryParserInterface;
use PoP\FieldQuery\Query\QueryHelpers;

class FieldQueryInterpreter implements FieldQueryInterpreterInterface
{
    // Cache the output from functions
    private $extractedFieldArgumentsCache = [];
    private $extractedFieldArgumentWarningsCache = [];
    private $fieldArgumentNameTypesCache = [];

    // Services
    private $translationAPI;
    private $errorMessageStore;
    private $typeCastingExecuter;
    private $queryParser;

    public function __construct(
        TranslationAPIInterface $translationAPI,
        ErrorMessageStoreInterface $errorMessageStore,
        TypeCastingExecuterInterface $typeCastingExecuter,
        QueryParserInterface $queryParser
    ) {
        $this->translationAPI = $translationAPI;
        $this->errorMessageStore = $errorMessageStore;
        $this->typeCastingExecuter = $typeCastingExecuter;
        $this->queryParser = $queryParser;
    }

    public function extractFieldArguments(FieldResolverInterface $fieldResolver, string $field, ?array &$schemaWarnings = null): array
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

    protected function doExtractFieldArguments(FieldResolverInterface $fieldResolver, string $field, ?array &$schemaWarnings = null): array
    {
        $fieldArgs = [];
        // Extract the args from the string into an array
        $fieldArgsStr = $this->getFieldArgs($field);
        // Remove the opening and closing brackets
        $fieldArgsStr = substr($fieldArgsStr, strlen(QuerySyntax::SYMBOL_FIELDARGS_OPENING), strlen($fieldArgsStr)-strlen(QuerySyntax::SYMBOL_FIELDARGS_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING));
        // Remove the white spaces before and after
        if ($fieldArgsStr = trim($fieldArgsStr)) {
            // Iterate all the elements, and extract them into the array
            if ($fieldArgElems = $this->queryParser->splitElements($fieldArgsStr, QuerySyntax::SYMBOL_FIELDARGS_ARGSEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING)) {
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

    public function extractFieldArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null): array
    {
        $schemaErrors = [];
        $schemaWarnings = [];
        $schemaDeprecations = [];
        $validAndResolvedField = $field;
        $fieldName = $this->getFieldName($field);
        $extractedFieldArgs = $fieldArgs = $this->extractFieldArguments($fieldResolver, $field, $schemaWarnings);
        if ($fieldArgs) {
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
            $validAndResolvedField = null;
        } else {
            // There are 2 reasons why the field might have changed:
            // 1. validField: There are $schemaWarnings: remove the fieldArgs that failed
            // 2. resolvedField: Some fieldArg was a variable: replace it with its value
            if ($extractedFieldArgs != $fieldArgs) {
                $validAndResolvedField = $this->replaceFieldArgs($field, $fieldArgs);
            }
        }
        return [
            $validAndResolvedField,
            $fieldName,
            $fieldArgs,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations,
        ];
    }

    public function extractDirectiveArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null): array
    {
        return $this->extractFieldArgumentsForSchema($fieldResolver, $field, $variables);
    }

    public function extractFieldArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $field, ?array $variables = null): array
    {
        $dbErrors = $dbWarnings = [];
        $validAndResolvedField = $field;
        $fieldName = $this->getFieldName($field);
        $extractedFieldArgs = $fieldArgs = $this->extractFieldArguments($fieldResolver, $field);

        // Only need to extract arguments if they have fields
        if (FieldQueryUtils::isAnyFieldArgumentValueAField(
            array_values(
                $fieldArgs
            )
        )) {
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
            if ($dbErrors) {
                $validAndResolvedField = null;
            } else {
                // There are 2 reasons why the field might have changed:
                // 1. validField: There are $dbWarnings: remove the fieldArgs that failed
                // 2. resolvedField: Some fieldArg was a variable: replace it with its value
                if ($extractedFieldArgs != $fieldArgs) {
                    $validAndResolvedField = $this->replaceFieldArgs($field, $fieldArgs);
                }
            }
        }
        return [
            $validAndResolvedField,
            $fieldName,
            $fieldArgs,
            $dbErrors,
            $dbWarnings
        ];
    }

    public function extractDirectiveArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $field, ?array $variables = null): array
    {
        return $this->extractFieldArgumentsForResultItem($fieldResolver, $resultItem, $field, $variables);
    }

    protected function castFieldArguments(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$failedCastingFieldArgErrorMessages, bool $forSchema): array
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

    protected function castFieldArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$failedCastingFieldArgErrorMessages): array
    {
        return $this->castFieldArguments($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages, true);
    }

    protected function castFieldArgumentsForResultItem(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$failedCastingFieldArgErrorMessages): array
    {
        return $this->castFieldArguments($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages, false);
    }

    protected function getFieldArgumentNameTypes(FieldResolverInterface $fieldResolver, string $field): array
    {
        if (!isset($this->fieldArgumentNameTypesCache[get_class($fieldResolver)][$field])) {
            $this->fieldArgumentNameTypesCache[get_class($fieldResolver)][$field] = $this->doGetFieldArgumentNameTypes($fieldResolver, $field);
        }
        return $this->fieldArgumentNameTypesCache[get_class($fieldResolver)][$field];
    }

    protected function doGetFieldArgumentNameTypes(FieldResolverInterface $fieldResolver, string $field): array
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

    protected function castAndValidateFieldArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$schemaWarnings): array
    {
        $failedCastingFieldArgErrorMessages = [];
        $castedFieldArgs = $this->castFieldArgumentsForSchema($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages);
        return $this->castAndValidateFieldArguments($fieldResolver, $castedFieldArgs, $failedCastingFieldArgErrorMessages, $field, $fieldArgs, $schemaWarnings);
    }

    protected function castAndValidateFieldArgumentsForResultItem(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$dbWarnings): array
    {
        $failedCastingFieldArgErrorMessages = [];
        $castedFieldArgs = $this->castFieldArgumentsForResultItem($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages);
        return $this->castAndValidateFieldArguments($fieldResolver, $castedFieldArgs, $failedCastingFieldArgErrorMessages, $field, $fieldArgs, $dbWarnings);
    }

    protected function castAndValidateFieldArguments(FieldResolverInterface $fieldResolver, array $castedFieldArgs, array &$failedCastingFieldArgErrorMessages, string $field, array $fieldArgs, array &$schemaWarnings): array
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
        // If it is a variable, retrieve the actual value from the request
        if ($this->isFieldArgumentValueAVariable($fieldArgValue)) {
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

    public function maybeConvertFieldArgumentArrayValueFromStringToArray(string $fieldArgValue)
    {
        // If surrounded by [...], it is an array
        if (substr($fieldArgValue, 0, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING && substr($fieldArgValue, -1*strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING) {
            $arrayValue = substr($fieldArgValue, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING), strlen($fieldArgValue)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING));
            // Elements are split by ";"
            return $this->queryParser->splitElements($arrayValue, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
        }

        return $fieldArgValue;
    }

    protected function maybeConvertFieldArgumentArrayValue(string $fieldArgValue, array $variables = null)
    {
        $fieldArgValue = $this->maybeConvertFieldArgumentArrayValueFromStringToArray($fieldArgValue);
        if (is_array($fieldArgValue)) {
            // Resolve each element the same way
            return $this->filterFieldArgs(array_map(function($arrayValueElem) use($variables) {
                return $this->maybeConvertFieldArgumentValue($arrayValueElem, $variables);
            }, $fieldArgValue));
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
    protected function maybeResolveFieldArgumentValueForResultItem(FieldResolverInterface $fieldResolver, $resultItem, $fieldArgValue, array $variables = null)
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

    protected function resolveFieldArgumentValueErrorDescriptionsForSchema(FieldResolverInterface $fieldResolver, $fieldArgValue, array $variables = null): ?array
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
                        $this->translationAPI->__('Field \'%s\' has arguments, but because the closing argument symbol \'%s\' is not at the end, it has been ignored', 'pop-component-model'),
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

    protected function resolveFieldArgumentValueWarningsForSchema(FieldResolverInterface $fieldResolver, $fieldArgValue, array $variables = null): ?array
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

    protected function resolveFieldArgumentValueDeprecationsForSchema(FieldResolverInterface $fieldResolver, $fieldArgValue, array $variables = null): ?array
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
}
