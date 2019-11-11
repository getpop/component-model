<?php
namespace PoP\ComponentModel\Schema;
use PoP\FieldQuery\QueryUtils;
use PoP\FieldQuery\QuerySyntax;
use PoP\FieldQuery\QueryHelpers;
use PoP\ComponentModel\ErrorUtils;
use PoP\FieldQuery\FieldQueryUtils;
use PoP\ComponentModel\GeneralUtils;
use PoP\QueryParsing\QueryParserInterface;
use PoP\Translation\TranslationAPIInterface;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\DirectiveResolvers\DirectiveResolverInterface;

class FieldQueryInterpreter extends \PoP\FieldQuery\FieldQueryInterpreter implements FieldQueryInterpreterInterface
{
    // Cache the output from functions
    private $extractedStaticFieldArgumentsCache = [];
    private $extractedFieldArgumentsCache = [];
    private $extractedDirectiveArgumentsCache = [];
    private $extractedFieldArgumentWarningsCache = [];
    private $extractedDirectiveArgumentWarningsCache = [];
    private $fieldArgumentNameTypesCache = [];
    private $directiveArgumentNameTypesCache = [];

    // Services
    protected $typeCastingExecuter;

    public function __construct(
        TranslationAPIInterface $translationAPI,
        FeedbackMessageStoreInterface $feedbackMessageStore,
        TypeCastingExecuterInterface $typeCastingExecuter,
        QueryParserInterface $queryParser
    ) {
        parent::__construct($translationAPI, $feedbackMessageStore, $queryParser);
        $this->typeCastingExecuter = $typeCastingExecuter;
    }

    /**
     * Extract field args without using the schema. It is needed to find out which fieldValueResolver will process a field, where we can't depend on the schema since this one needs to know who the fieldValueResolver is, creating an infitine loop
     * Directive arguments have the same syntax as field arguments, so simply re-utilize the corresponding function for field arguments
     *
     * @param FieldResolverInterface $fieldResolver
     * @param string $field
     * @return array
     */
    public function extractStaticDirectiveArguments(string $directive, ?array $variables = null): array
    {
        return $this->extractStaticFieldArguments($directive, $variables);
    }

    protected function getVariablesHash(?array $variables): string
    {
        return (string)hash('crc32', json_encode($variables ?? []));
    }

    /**
     * Extract field args without using the schema. It is needed to find out which fieldValueResolver will process a field, where we can't depend on the schema since this one needs to know who the fieldValueResolver is, creating an infitine loop
     *
     * @param FieldResolverInterface $fieldResolver
     * @param string $field
     * @return array
     */
    public function extractStaticFieldArguments(string $field, ?array $variables = null): array
    {
        $variablesHash = $this->getVariablesHash($variables);
        if (!isset($this->extractedStaticFieldArgumentsCache[$field][$variablesHash])) {
            $this->extractedStaticFieldArgumentsCache[$field][$variablesHash] = $this->doExtractStaticFieldArguments($field, $variables);
        }
        return $this->extractedStaticFieldArgumentsCache[$field][$variablesHash];
    }

    protected function doExtractStaticFieldArguments(string $field, ?array $variables): array
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
                for ($i=0; $i<count($fieldArgElems); $i++) {
                    $fieldArg = $fieldArgElems[$i];
                    // If there is no separator, then skip this arg, since it is not static (without the schema, we can't know which fieldArgName it is)
                    $separatorPos = QueryUtils::findFirstSymbolPosition($fieldArg, QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
                    if ($separatorPos === false) {
                        continue;
                    }
                    $fieldArgName = trim(substr($fieldArg, 0, $separatorPos));
                    $fieldArgValue = trim(substr($fieldArg, $separatorPos + strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR)));
                    // If the field is an array in its string representation, convert it to array
                    $fieldArgValue = $this->maybeConvertFieldArgumentValue($fieldArgValue, $variables);
                    $fieldArgs[$fieldArgName] = $fieldArgValue;
                }
            }
        }

        return $fieldArgs;
    }

    public function extractDirectiveArguments(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $fieldDirective, ?array $variables = null, ?array &$schemaWarnings = null): array
    {
        $variablesHash = $this->getVariablesHash($variables);
        if (!isset($this->extractedDirectiveArgumentsCache[get_class($fieldResolver)][$fieldDirective][$variablesHash])) {
            $fieldSchemaWarnings = [];
            $this->extractedDirectiveArgumentsCache[get_class($fieldResolver)][$fieldDirective][$variablesHash] = $this->doExtractDirectiveArguments($directiveResolver, $fieldResolver, $fieldDirective, $variables, $fieldSchemaWarnings);
            // Also cache the schemaWarnings
            if (!is_null($schemaWarnings)) {
                $this->extractedDirectiveArgumentWarningsCache[get_class($fieldResolver)][$fieldDirective][$variablesHash] = $fieldSchemaWarnings;
            }
        }
        // Integrate the schemaWarnings too
        if (!is_null($schemaWarnings)) {
            $schemaWarnings = array_merge(
                $schemaWarnings,
                $this->extractedDirectiveArgumentWarningsCache[get_class($fieldResolver)][$fieldDirective][$variablesHash]
            );
        }
        return $this->extractedDirectiveArgumentsCache[get_class($fieldResolver)][$fieldDirective][$variablesHash];
    }

    protected function doExtractDirectiveArguments(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $fieldDirective, ?array $variables, array &$schemaWarnings): array
    {
        // Iterate all the elements, and extract them into the array
        if ($directiveArgElems = QueryHelpers::getFieldArgElements($this->getFieldDirectiveArgs($fieldDirective))) {
            $directiveArgumentNameTypes = $this->getDirectiveArgumentNameTypes($directiveResolver, $fieldResolver, $fieldDirective);
            $orderedDirectiveArgNamesEnabled = $directiveResolver->enableOrderedSchemaDirectiveArgs($fieldResolver);
            return $this->extractAndValidateFielOrDirectiveArguments(
                $fieldDirective,
                $directiveArgElems,
                $orderedDirectiveArgNamesEnabled,
                $directiveArgumentNameTypes,
                $variables,
                $schemaWarnings
            );
        }

        return [];
    }

    /**
     * Extract the arguments for either the field or directive. If the argument name has not been provided, attempt to deduce it from the schema, or show a warning if not possible
     *
     * @param string $fieldOrDirective
     * @param array $fieldOrDirectiveArgElems
     * @param boolean $orderedFieldOrDirectiveArgNamesEnabled
     * @param array $fieldOrDirectiveArgumentNameTypes
     * @param array|null $variables
     * @param array $schemaWarnings
     * @return array
     */
    protected function extractAndValidateFielOrDirectiveArguments(
        string $fieldOrDirective,
        array $fieldOrDirectiveArgElems,
        bool $orderedFieldOrDirectiveArgNamesEnabled,
        array $fieldOrDirectiveArgumentNameTypes,
        ?array $variables,
        array &$schemaWarnings
    ): array
    {
        if ($orderedFieldOrDirectiveArgNamesEnabled) {
            $orderedFieldOrDirectiveArgNames = array_keys($fieldOrDirectiveArgumentNameTypes);
        }
        $fieldOrDirectiveArgs = [];
        for ($i=0; $i<count($fieldOrDirectiveArgElems); $i++) {
            $fieldOrDirectiveArg = $fieldOrDirectiveArgElems[$i];
            // Either one of 2 formats are accepted:
            // 1. The key:value pair
            // 2. Only the value, and extract the key from the schema definition (if enabled for that fieldOrDirective)
            $separatorPos = QueryUtils::findFirstSymbolPosition($fieldOrDirectiveArg, QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
            if ($separatorPos === false) {
                $fieldOrDirectiveArgValue = $fieldOrDirectiveArg;
                if (!$orderedFieldOrDirectiveArgNamesEnabled || !isset($orderedFieldOrDirectiveArgNames[$i])) {
                    $errorMessage = $orderedFieldOrDirectiveArgNamesEnabled ?
                        $this->translationAPI->__('documentation for this argument in the schema definition has not been defined, hence it can\'t be deduced from there', 'pop-component-model') :
                        $this->translationAPI->__('retrieving this information from the schema definition is disabled for the corresponding “fieldResolver”', 'pop-component-model');
                    $schemaWarnings[] = sprintf(
                        $this->translationAPI->__('The argument on position number %s (with value \'%s\') has its name missing, and %s. Please define the query using the \'key%svalue\' format. This argument has been ignored', 'pop-component-model'),
                        $i+1,
                        $fieldOrDirectiveArgValue,
                        $errorMessage,
                        QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR
                    );
                    // Ignore extracting this argument
                    continue;
                }
                $fieldOrDirectiveArgName = $orderedFieldOrDirectiveArgNames[$i];
                // Log the found fieldOrDirectiveArgName
                $this->feedbackMessageStore->addLogEntry(
                    sprintf(
                        $this->translationAPI->__('In field or directive \'%s\', the argument on position number %s (with value \'%s\') is resolved as argument \'%s\'', 'pop-component-model'),
                        $fieldOrDirective,
                        $i+1,
                        $fieldOrDirectiveArgValue,
                        $fieldOrDirectiveArgName
                    )
                );
            } else {
                $fieldOrDirectiveArgName = trim(substr($fieldOrDirectiveArg, 0, $separatorPos));
                $fieldOrDirectiveArgValue = trim(substr($fieldOrDirectiveArg, $separatorPos + strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR)));
                // Validate that this argument exists in the schema, or show a warning if not
                // But don't skip it! It may be that the engine accepts the property, it is just not documented!
                if (!array_key_exists($fieldOrDirectiveArgName, $fieldOrDirectiveArgumentNameTypes)) {
                    $schemaWarnings[] = sprintf(
                        $this->translationAPI->__('Argument with name \'%s\' has not been documented in the schema, so it may have no effect (it has not been removed from the query, though)', 'pop-component-model'),
                        $fieldOrDirectiveArgName
                    );
                }
            }

            // If the field is an array in its string representation, convert it to array
            $fieldOrDirectiveArgValue = $this->maybeConvertFieldArgumentValue($fieldOrDirectiveArgValue, $variables);
            $fieldOrDirectiveArgs[$fieldOrDirectiveArgName] = $fieldOrDirectiveArgValue;
        }

        return $fieldOrDirectiveArgs;
    }

    public function extractFieldArguments(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null, ?array &$schemaWarnings = null): array
    {
        $variablesHash = $this->getVariablesHash($variables);
        if (!isset($this->extractedFieldArgumentsCache[get_class($fieldResolver)][$field][$variablesHash])) {
            $fieldSchemaWarnings = [];
            $this->extractedFieldArgumentsCache[get_class($fieldResolver)][$field][$variablesHash] = $this->doExtractFieldArguments($fieldResolver, $field, $variables, $fieldSchemaWarnings);
            // Also cache the schemaWarnings
            if (!is_null($schemaWarnings)) {
                $this->extractedFieldArgumentWarningsCache[get_class($fieldResolver)][$field][$variablesHash] = $fieldSchemaWarnings;
            }
        }
        // Integrate the schemaWarnings too
        if (!is_null($schemaWarnings)) {
            $schemaWarnings = array_merge(
                $schemaWarnings,
                $this->extractedFieldArgumentWarningsCache[get_class($fieldResolver)][$field][$variablesHash]
            );
        }
        return $this->extractedFieldArgumentsCache[get_class($fieldResolver)][$field][$variablesHash];
    }

    protected function doExtractFieldArguments(FieldResolverInterface $fieldResolver, string $field, ?array $variables, array &$schemaWarnings): array
    {
        // Iterate all the elements, and extract them into the array
        if ($fieldArgElems = QueryHelpers::getFieldArgElements($this->getFieldArgs($field))) {
            $fieldArgumentNameTypes = $this->getFieldArgumentNameTypes($fieldResolver, $field);
            $orderedFieldArgNamesEnabled = $fieldResolver->enableOrderedSchemaFieldArgs($field);
            return $this->extractAndValidateFielOrDirectiveArguments(
                $field,
                $fieldArgElems,
                $orderedFieldArgNamesEnabled,
                $fieldArgumentNameTypes,
                $variables,
                $schemaWarnings
            );
        }

        return [];
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
        $extractedFieldArgs = $fieldArgs = $this->extractFieldArguments($fieldResolver, $field, $variables, $schemaWarnings);
        $fieldArgs = $this->validateExtractedFieldOrDirectiveArgumentsForSchema($fieldResolver, $fieldArgs, $variables, $schemaErrors, $schemaWarnings, $schemaDeprecations);
        // Cast the values to their appropriate type. If casting fails, the value returns as null
        $fieldArgs = $this->castAndValidateFieldArgumentsForSchema($fieldResolver, $field, $fieldArgs, $schemaWarnings);
        // Enable the fieldResolver to add its own code validations
        $fieldArgs = $fieldResolver->validateFieldArgumentsForSchema($field, $fieldArgs, $schemaErrors, $schemaWarnings, $schemaDeprecations);

        // If there's an error, those args will be removed. Then, re-create the fieldDirective to pass it to the function below
        if ($schemaErrors) {
            $validAndResolvedField = null;
        } elseif ($extractedFieldArgs != $fieldArgs) {
            // There are 2 reasons why the field might have changed:
            // 1. validField: There are $schemaWarnings: remove the fieldArgs that failed
            // 2. resolvedField: Some fieldArg was a variable: replace it with its value
            $validAndResolvedField = $this->replaceFieldArgs($field, $fieldArgs);
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

    public function extractDirectiveArgumentsForSchema(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $fieldDirective, ?array $variables = null): array
    {
        $schemaErrors = [];
        $schemaWarnings = [];
        $schemaDeprecations = [];
        $validAndResolvedDirective = $fieldDirective;
        $directiveName = $this->getFieldDirectiveName($fieldDirective);
        $extractedDirectiveArgs = $directiveArgs = $this->extractDirectiveArguments($directiveResolver, $fieldResolver, $fieldDirective, $variables, $schemaWarnings);
        $directiveArgs = $this->validateExtractedFieldOrDirectiveArgumentsForSchema($fieldResolver, $directiveArgs, $variables, $schemaErrors, $schemaWarnings, $schemaDeprecations);
        // Cast the values to their appropriate type. If casting fails, the value returns as null
        $directiveArgs = $this->castAndValidateDirectiveArgumentsForSchema($directiveResolver, $fieldResolver, $fieldDirective, $directiveArgs, $schemaWarnings);
        // Enable the directiveResolver to add its own code validations
        $directiveArgs = $directiveResolver->validateDirectiveArgumentsForSchema($fieldResolver, $directiveArgs, $schemaErrors, $schemaWarnings, $schemaDeprecations);

        // If there's an error, those args will be removed. Then, re-create the fieldDirective to pass it to the function below
        if ($schemaErrors) {
            $validAndResolvedDirective = null;
        } elseif ($extractedDirectiveArgs != $directiveArgs) {
            // There are 2 reasons why the fieldDirective might have changed:
            // 1. validField: There are $schemaWarnings: remove the directiveArgs that failed
            // 2. resolvedField: Some directiveArg was a variable: replace it with its value
            $validAndResolvedDirective = $this->replaceFieldArgs($fieldDirective, $directiveArgs);
        }
        return [
            $validAndResolvedDirective,
            $directiveName,
            $directiveArgs,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations,
        ];
    }

    protected function validateExtractedFieldOrDirectiveArgumentsForSchema(FieldResolverInterface $fieldResolver, array $fieldOrDirectiveArgs, ?array $variables, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        if ($fieldOrDirectiveArgs) {
            foreach ($fieldOrDirectiveArgs as $argName => $argValue) {
                // Validate it
                if ($maybeErrors = $this->resolveFieldArgumentValueErrorDescriptionsForSchema($fieldResolver, $argValue, $variables)) {
                    $schemaErrors = array_merge(
                        $schemaErrors,
                        $maybeErrors
                    );
                    // Because it's an error, set the value to null, so it will be filtered out
                    $fieldOrDirectiveArgs[$argName] = null;
                }
                // Find warnings and deprecations
                if ($maybeWarnings = $this->resolveFieldArgumentValueWarningsForSchema($fieldResolver, $argValue, $variables)) {
                    $schemaWarnings = array_merge(
                        $schemaWarnings,
                        $maybeWarnings
                    );
                }
                if ($maybeDeprecations = $this->resolveFieldArgumentValueDeprecationsForSchema($fieldResolver, $argValue, $variables)) {
                    $schemaDeprecations = array_merge(
                        $schemaDeprecations,
                        $maybeDeprecations
                    );
                }
            }
            // If there was an error, remove those entries
            $fieldOrDirectiveArgs = $this->filterFieldArgs($fieldOrDirectiveArgs);
        }
        return $fieldOrDirectiveArgs;
    }

    public function extractFieldArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $field, ?array $variables = null): array
    {
        $dbErrors = $dbWarnings = [];
        $validAndResolvedField = $field;
        $fieldName = $this->getFieldName($field);
        $extractedFieldArgs = $fieldArgs = $this->extractFieldArguments($fieldResolver, $field, $variables);
        // Only need to extract arguments if they have fields or arrays
        $fieldOutputKey = $this->getFieldOutputKey($field);
        $fieldArgs = $this->extractFieldOrDirectiveArgumentsForResultItem($fieldResolver, $resultItem, $fieldArgs, $fieldOutputKey, $variables, $dbErrors);
        // Cast the values to their appropriate type. If casting fails, the value returns as null
        $resultItemDBWarnings = [];
        $fieldArgs = $this->castAndValidateFieldArgumentsForResultItem($fieldResolver, $field, $fieldArgs, $resultItemDBWarnings);
        if ($resultItemDBWarnings) {
            $id = $fieldResolver->getId($resultItem);
            $dbWarnings[(string)$id][$fieldOutputKey] = array_merge(
                $dbWarnings[(string)$id][$fieldOutputKey] ?? [],
                $resultItemDBWarnings
            );
        }
        if ($dbErrors) {
            $validAndResolvedField = null;
        } elseif ($extractedFieldArgs != $fieldArgs) {
            // There are 2 reasons why the field might have changed:
            // 1. validField: There are $dbWarnings: remove the fieldArgs that failed
            // 2. resolvedField: Some fieldArg was a variable: replace it with its value
            $validAndResolvedField = $this->replaceFieldArgs($field, $fieldArgs);
        }
        return [
            $validAndResolvedField,
            $fieldName,
            $fieldArgs,
            $dbErrors,
            $dbWarnings
        ];
    }

    public function extractDirectiveArgumentsForResultItem(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, $resultItem, string $fieldDirective, ?array $variables = null): array
    {
        $dbErrors = $dbWarnings = [];
        $validAndResolvedDirective = $fieldDirective;
        $directiveName = $this->getFieldDirectiveName($fieldDirective);
        $extractedDirectiveArgs = $directiveArgs = $this->extractDirectiveArguments($directiveResolver, $fieldResolver, $fieldDirective, $variables);
        // Only need to extract arguments if they have fields or arrays
        $directiveOutputKey = $this->getDirectiveOutputKey($fieldDirective);
        $directiveArgs = $this->extractFieldOrDirectiveArgumentsForResultItem($fieldResolver, $resultItem, $directiveArgs, $directiveOutputKey, $variables, $dbErrors);
        // Cast the values to their appropriate type. If casting fails, the value returns as null
        $resultItemDBWarnings = [];
        $directiveArgs = $this->castAndValidateDirectiveArgumentsForResultItem($directiveResolver, $fieldResolver, $fieldDirective, $directiveArgs, $resultItemDBWarnings);
        if ($resultItemDBWarnings) {
            $id = $fieldResolver->getId($resultItem);
            $dbWarnings[(string)$id][$directiveOutputKey] = array_merge(
                $dbWarnings[(string)$id][$directiveOutputKey] ?? [],
                $resultItemDBWarnings
            );
        }
        if ($dbErrors) {
            $validAndResolvedDirective = null;
        } elseif ($extractedDirectiveArgs != $directiveArgs) {
            // There are 2 reasons why the fieldDirective might have changed:
            // 1. validField: There are $dbWarnings: remove the directiveArgs that failed
            // 2. resolvedField: Some directiveArg was a variable: replace it with its value
            $validAndResolvedDirective = $this->replaceFieldArgs($fieldDirective, $directiveArgs);
        }
        return [
            $validAndResolvedDirective,
            $directiveName,
            $directiveArgs,
            $dbErrors,
            $dbWarnings
        ];
    }

    protected function extractFieldOrDirectiveArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, array $fieldOrDirectiveArgs, string $fieldOrDirectiveOutputKey, ?array $variables, array &$dbErrors): array
    {
        // Only need to extract arguments if they have fields or arrays
        if (FieldQueryUtils::isAnyFieldArgumentValueDynamic(
            array_values(
                $fieldOrDirectiveArgs
            )
        )) {
            $id = $fieldResolver->getId($resultItem);
            foreach ($fieldOrDirectiveArgs as $directiveArgName => $directiveArgValue) {
                $directiveArgValue = $this->maybeResolveFieldArgumentValueForResultItem($fieldResolver, $resultItem, $directiveArgValue, $variables);
                // Validate it
                if (\PoP\ComponentModel\GeneralUtils::isError($directiveArgValue)) {
                    $error = $directiveArgValue;
                    if ($errorData = $error->getErrorData()) {
                        $errorOutputKey = $errorData[ErrorUtils::ERRORDATA_FIELD_NAME];
                    }
                    $errorOutputKey = $errorOutputKey ?? $fieldOrDirectiveOutputKey;
                    $dbErrors[(string)$id][$errorOutputKey][] = $error->getErrorMessage();
                    $fieldOrDirectiveArgs[$directiveArgName] = null;
                    continue;
                }
                $fieldOrDirectiveArgs[$directiveArgName] = $directiveArgValue;
            }
            return $this->filterFieldArgs($fieldOrDirectiveArgs);
        }
        return $fieldOrDirectiveArgs;
    }

    protected function castDirectiveArguments(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $directive, array $directiveArgs, array &$failedCastingDirectiveArgErrorMessages, bool $forSchema): array
    {
        // Get the field argument types, to know to what type it will cast the value
        if ($directiveArgNameTypes = $this->getDirectiveArgumentNameTypes($directiveResolver, $fieldResolver)) {
            return $this->castFieldOrDirectiveArguments($directiveArgs, $directiveArgNameTypes, $failedCastingDirectiveArgErrorMessages, $forSchema);
        }
        return $directiveArgs;
    }

    protected function castFieldArguments(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$failedCastingFieldArgErrorMessages, bool $forSchema): array
    {
        // Get the field argument types, to know to what type it will cast the value
        if ($fieldArgNameTypes = $this->getFieldArgumentNameTypes($fieldResolver, $field)) {
            return $this->castFieldOrDirectiveArguments($fieldArgs, $fieldArgNameTypes, $failedCastingFieldArgErrorMessages, $forSchema);
        }
        return $fieldArgs;
    }

    protected function castFieldOrDirectiveArguments(array $fieldOrDirectiveArgs, array $fieldOrDirectiveArgNameTypes, array &$failedCastingFieldOrDirectiveArgErrorMessages, bool $forSchema): array
    {
        // Cast all argument values
        foreach ($fieldOrDirectiveArgs as $argName => $argValue) {
            // Maybe cast the value to the appropriate type. Eg: from string to boolean
            if ($fieldArgType = $fieldOrDirectiveArgNameTypes[$argName]) {
                // There are 2 possibilities for casting:
                // 1. $forSchema = true: Cast all items except fields (eg: has-comments())
                // 2. $forSchema = false: Should be cast only fields, however by now we can't tell which are fields and which are not, since fields have already been resolved to their value. Hence, cast everything (fieldArgValues that failed at the schema level will not be provided in the input array, so won't be validated twice)
                // Otherwise, simply add the argValue directly, it will be eventually casted by the other function
                if (
                    ($forSchema && !$this->isFieldArgumentValueDynamic($argValue)) ||
                    !$forSchema
                ) {
                    // If the value is an array, and the type is a combination of types, then cast each element to the item type
                    $isArray = false;
                    if ($maybeFieldArgTypeCombiningElems = TypeCastingHelpers::maybeGetTypeCombinationElements($fieldArgType)) {
                        $isArray = $maybeFieldArgTypeCombiningElems[0] == SchemaDefinition::TYPE_ARRAY && is_array($argValue);
                    }
                    if ($isArray) {
                        $arrayFieldArgType = $maybeFieldArgTypeCombiningElems[1];
                        $argValue = array_map(
                            function($arrayArgValueElem) use($arrayFieldArgType) {
                                return $this->typeCastingExecuter->cast($arrayFieldArgType, $arrayArgValueElem);
                            },
                            $argValue
                        );
                    } else {
                        // Otherwise, simply cast the given value directly
                        $argValue = $this->typeCastingExecuter->cast($fieldArgType, $argValue);
                    }
                    // If the response is an error, extract the error message and set value to null
                    if (GeneralUtils::isError($argValue)) {
                        $error = $argValue;
                        $failedCastingFieldOrDirectiveArgErrorMessages[$argName] = $error->getErrorMessage();
                        $fieldOrDirectiveArgs[$argName] = null;
                        continue;
                    }
                    $fieldOrDirectiveArgs[$argName] = $argValue;
                }
            }
        }
        return $fieldOrDirectiveArgs;
    }

    protected function castDirectiveArgumentsForSchema(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $fieldDirective, array $directiveArgs, array &$failedCastingDirectiveArgErrorMessages): array
    {
        return $this->castDirectiveArguments($directiveResolver, $fieldResolver, $fieldDirective, $directiveArgs, $failedCastingDirectiveArgErrorMessages, true);
    }

    protected function castFieldArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$failedCastingFieldArgErrorMessages): array
    {
        return $this->castFieldArguments($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages, true);
    }

    protected function castDirectiveArgumentsForResultItem(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $directive, array $directiveArgs, array &$failedCastingDirectiveArgErrorMessages): array
    {
        return $this->castDirectiveArguments($directiveResolver, $fieldResolver, $directive, $directiveArgs, $failedCastingDirectiveArgErrorMessages, false);
    }

    protected function castFieldArgumentsForResultItem(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$failedCastingFieldArgErrorMessages): array
    {
        return $this->castFieldArguments($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages, false);
    }

    protected function getDirectiveArgumentNameTypes(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver): array
    {
        if (!isset($this->directiveArgumentNameTypesCache[get_class($directiveResolver)][get_class($fieldResolver)])) {
            $this->directiveArgumentNameTypesCache[get_class($directiveResolver)][get_class($fieldResolver)] = $this->doGetDirectiveArgumentNameTypes($directiveResolver, $fieldResolver);
        }
        return $this->directiveArgumentNameTypesCache[get_class($directiveResolver)][get_class($fieldResolver)];
    }

    protected function doGetDirectiveArgumentNameTypes(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver): array
    {
        // Get the fieldDirective argument types, to know to what type it will cast the value
        $directiveArgNameTypes = [];
        if ($directiveSchemaDefinitionArgs = $directiveResolver->getSchemaDirectiveArgs($fieldResolver)) {
            foreach ($directiveSchemaDefinitionArgs as $directiveSchemaDefinitionArg) {
                $directiveArgNameTypes[$directiveSchemaDefinitionArg['name']] = $directiveSchemaDefinitionArg['type'];
            }
        }
        return $directiveArgNameTypes;
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
        if ($fieldSchemaDefinitionArgs = $fieldResolver->getSchemaFieldArgs($field)) {
            foreach ($fieldSchemaDefinitionArgs as $fieldSchemaDefinitionArg) {
                $fieldArgNameTypes[$fieldSchemaDefinitionArg['name']] = $fieldSchemaDefinitionArg['type'];
            }
        }
        return $fieldArgNameTypes;
    }

    protected function castAndValidateDirectiveArgumentsForSchema(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $fieldDirective, array $directiveArgs, array &$schemaWarnings): array
    {
        if ($directiveArgs) {
            $failedCastingDirectiveArgErrorMessages = [];
            $castedDirectiveArgs = $this->castDirectiveArgumentsForSchema($directiveResolver, $fieldResolver, $fieldDirective, $directiveArgs, $failedCastingDirectiveArgErrorMessages);
            return $this->castAndValidateDirectiveArguments($directiveResolver, $fieldResolver, $castedDirectiveArgs, $failedCastingDirectiveArgErrorMessages, $fieldDirective, $directiveArgs, $schemaWarnings);
        }
        return $directiveArgs;
    }

    protected function castAndValidateFieldArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$schemaWarnings): array
    {
        if ($fieldArgs) {
            $failedCastingFieldArgErrorMessages = [];
            $castedFieldArgs = $this->castFieldArgumentsForSchema($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages);
            return $this->castAndValidateFieldArguments($fieldResolver, $castedFieldArgs, $failedCastingFieldArgErrorMessages, $field, $fieldArgs, $schemaWarnings);
        }
        return $fieldArgs;
    }

    protected function castAndValidateDirectiveArgumentsForResultItem(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $fieldDirective, array $directiveArgs, array &$dbWarnings): array
    {
        $failedCastingDirectiveArgErrorMessages = [];
        $castedDirectiveArgs = $this->castDirectiveArgumentsForResultItem($directiveResolver, $fieldResolver, $fieldDirective, $directiveArgs, $failedCastingDirectiveArgErrorMessages);
        return $this->castAndValidateDirectiveArguments($directiveResolver, $fieldResolver, $castedDirectiveArgs, $failedCastingDirectiveArgErrorMessages, $fieldDirective, $directiveArgs, $dbWarnings);
    }

    protected function castAndValidateFieldArgumentsForResultItem(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$dbWarnings): array
    {
        $failedCastingFieldArgErrorMessages = [];
        $castedFieldArgs = $this->castFieldArgumentsForResultItem($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages);
        return $this->castAndValidateFieldArguments($fieldResolver, $castedFieldArgs, $failedCastingFieldArgErrorMessages, $field, $fieldArgs, $dbWarnings);
    }

    protected function castAndValidateDirectiveArguments(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, array $castedDirectiveArgs, array &$failedCastingDirectiveArgErrorMessages, string $fieldDirective, array $directiveArgs, array &$schemaWarnings): array
    {
        // If any casting can't be done, show an error
        if ($failedCastingDirectiveArgs = array_filter($castedDirectiveArgs, function($directiveArgValue) {
            return is_null($directiveArgValue);
        })) {
            $directiveName = $this->getFieldDirectiveName($fieldDirective);
            $directiveArgNameTypes = $this->getDirectiveArgumentNameTypes($directiveResolver, $fieldResolver);
            foreach (array_keys($failedCastingDirectiveArgs) as $failedCastingDirectiveArgName) {
                // If it is Error, also show the error message
                if ($directiveArgErrorMessage = $failedCastingDirectiveArgErrorMessages[$failedCastingDirectiveArgName]) {
                    $errorMessage = sprintf(
                        $this->translationAPI->__('For directive \'%s\', casting value \'%s\' for argument \'%s\' to type \'%s\' failed: %s. It has been ignored', 'pop-component-model'),
                        $directiveName,
                        $directiveArgs[$failedCastingDirectiveArgName],
                        $failedCastingDirectiveArgName,
                        $directiveArgNameTypes[$failedCastingDirectiveArgName],
                        $directiveArgErrorMessage
                    );
                } else {
                    $errorMessage = sprintf(
                        $this->translationAPI->__('For directive \'%s\', casting value \'%s\' for argument \'%s\' to type \'%s\' failed, so it has been ignored', 'pop-component-model'),
                        $directiveName,
                        $directiveArgs[$failedCastingDirectiveArgName],
                        $failedCastingDirectiveArgName,
                        $directiveArgNameTypes[$failedCastingDirectiveArgName]
                    );
                }
                $schemaWarnings[] = $errorMessage;
            }
            return $this->filterFieldArgs($castedDirectiveArgs);
        }
        return $castedDirectiveArgs;
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
    protected function maybeConvertFieldArgumentValue($fieldArgValue, ?array $variables)
    {
        if (is_string($fieldArgValue)) {
            // Remove the white spaces before and after
            if ($fieldArgValue = trim($fieldArgValue)) {
                // Special case: when wrapping a string between quotes (eg: to avoid it being treated as a field, such as: posts(searchfor:"image(vertical)")),
                // the quotes are converted, from:
                // "value"
                // to:
                // "\"value\""
                // Transform back. Keep the quotes so that the string is still not converted to a field
                $fieldArgValue = stripcslashes($fieldArgValue);

                // If it has quotes at the beginning and end, it's a string. Remove them
                if ($this->isFieldArgumentValueWrappedWithStringSymbols($fieldArgValue)) {
                    $fieldArgValue = substr($fieldArgValue, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING), strlen($fieldArgValue)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING));
                }

                // Chain functions. At any moment, if any of them throws an error, the result will be null so don't process anymore
                // First replace all variables
                if ($fieldArgValue = $this->maybeConvertFieldArgumentVariableValue($fieldArgValue, $variables)) {
                    // Then convert to arrays
                    $fieldArgValue = $this->maybeConvertFieldArgumentArrayValue($fieldArgValue, $variables);
                }
            }
        }

        return $fieldArgValue;
    }

    protected function isFieldArgumentValueWrappedWithStringSymbols($fieldArgValue): bool
    {
        return
            substr($fieldArgValue, 0, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING &&
            substr($fieldArgValue, -1*strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING;
    }

    protected function maybeConvertFieldArgumentVariableValue($fieldArgValue, ?array $variables)
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
            $this->feedbackMessageStore->addQueryError(sprintf(
                $this->translationAPI->__('Variable \'%s\' is undefined', 'pop-component-model'),
                $variableName
            ));
            return null;
        }

        return $fieldArgValue;
    }

    protected function maybeConvertFieldArgumentArrayValueFromStringToArray(string $fieldArgValue)
    {
        // If surrounded by [...], it is an array
        if ($this->isFieldArgumentValueAnArrayRepresentedAsString($fieldArgValue)) {
            $arrayValue = substr($fieldArgValue, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING), strlen($fieldArgValue)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING));
            // Elements are split by ","
            $fieldArgValueElems = $this->queryParser->splitElements($arrayValue, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
            // Watch out! If calling with value "[true,false]" it gets transformed to "[1,]" when passing the nested field around (it's converted back to string),
            // This must be transformed to array(true, false), however the last empty space is ignored by `splitElements`
            // So we handle these 2 cases (empty spaces at beginning and end of string) in an exceptional way
            if (substr($arrayValue, 0, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_SEPARATOR)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_SEPARATOR) {
                array_unshift($fieldArgValueElems, '');
            }
            if (substr($arrayValue, -1*strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_SEPARATOR)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_SEPARATOR) {
                $fieldArgValueElems[] = '';
            }
            // Iterate all the elements and assign them to the fieldArgValue variable
            // Arrays can be single or double-dimensional (key => value)
            // Each element can define which case it is
            // 1. Single dimensional: just output the value: [value]
            // 2. Double dimensional: output the key, then "=", then the value: [key=value]
            // These 2 can be combined, and the corresponding array will mix elements: [value1,key2=value2]
            $fieldArgValue = [];
            foreach ($fieldArgValueElems as $fieldArgValueElem) {
                $fieldArgValueElemComponents = $this->queryParser->splitElements($fieldArgValueElem, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_KEYVALUEDELIMITER, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
                if (count($fieldArgValueElemComponents) == 1) {
                    $fieldArgValue[] = $fieldArgValueElemComponents[0];
                } else {
                    $fieldArgValue[$fieldArgValueElemComponents[0]] = $fieldArgValueElemComponents[1];
                }
            }
        }

        return $fieldArgValue;
    }

    protected function maybeConvertFieldArgumentArrayValue($fieldArgValue, ?array $variables)
    {
        if (is_string($fieldArgValue)) {
            $fieldArgValue = $this->maybeConvertFieldArgumentArrayValueFromStringToArray($fieldArgValue);
        }
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
    protected function maybeResolveFieldArgumentValueForResultItem(FieldResolverInterface $fieldResolver, $resultItem, $fieldArgValue, ?array $variables)
    {
        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return array_map(function($fieldArgValueElem) use($fieldResolver, $resultItem, $variables) {
                return $this->maybeResolveFieldArgumentValueForResultItem($fieldResolver, $resultItem, $fieldArgValueElem, $variables);
            }, (array)$fieldArgValue);
        }

        // Execute as expression
        if ($this->isFieldArgumentValueAnExpression($fieldArgValue)) {
            // Expressions: allow to pass a field argument "key:%input", which is passed when executing the directive through $expressions
            $expressions = $variables ?? $this->getVariablesFromRequest();
            $expressionName = substr($fieldArgValue, strlen(QuerySyntax::SYMBOL_EXPRESSION_OPENING), strlen($fieldArgValue)-strlen(QuerySyntax::SYMBOL_EXPRESSION_OPENING)-strlen(QuerySyntax::SYMBOL_EXPRESSION_CLOSING));
            if (isset($expressions[$expressionName])) {
                return $expressions[$expressionName];
            }
            // If the expression is not set, then show the error under entry "expressionErrors"
            $this->feedbackMessageStore->addQueryError(sprintf(
                $this->translationAPI->__('Expression \'%s\' is undefined', 'pop-component-model'),
                $expressionName
            ));
            return null;
        } elseif ($this->isFieldArgumentValueAField($fieldArgValue)) {
            // Execute as field
            return $fieldResolver->resolveValue($resultItem, (string)$fieldArgValue, $variables);
        }

        return $fieldArgValue;
    }

    protected function resolveFieldArgumentValueErrorDescriptionsForSchema(FieldResolverInterface $fieldResolver, $fieldArgValue, ?array $variables): ?array
    {
        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return GeneralUtils::arrayFlatten(array_filter(array_map(function($fieldArgValueElem) use($fieldResolver, $variables) {
                return $this->resolveFieldArgumentValueErrorDescriptionsForSchema($fieldResolver, $fieldArgValueElem, $variables);
            }, $fieldArgValue)));
        }

        // If the result fieldArgValue is a string (i.e. not numeric), and it has brackets (...),
        // and is not wrapped with quotes, then it is a field. Validate it and resolve it
        if (!empty($fieldArgValue) && is_string($fieldArgValue) && !is_numeric($fieldArgValue) && !$this->isFieldArgumentValueWrappedWithStringSymbols($fieldArgValue)) {

            $fieldArgValue = (string)$fieldArgValue;
            // If it has the fieldArg brackets, and the last bracket is at the end, then it's a field!
            list(
                $fieldArgsOpeningSymbolPos,
                $fieldArgsClosingSymbolPos
            ) = QueryHelpers::listFieldArgsSymbolPositions($fieldArgValue);

            // If there is no "(" or ")", or if the ")" is not at the end, of if the "(" is at the beginning, then it's simply a string
            if ($fieldArgsClosingSymbolPos !== (strlen($fieldArgValue)-strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING)) || $fieldArgsOpeningSymbolPos === false || $fieldArgsOpeningSymbolPos === 0) {
                return null;
            }
            // // If there is only one of them, it's a query error, so discard the query bit
            // if (($fieldArgsClosingSymbolPos === false && $fieldArgsOpeningSymbolPos !== false) || ($fieldArgsClosingSymbolPos !== false && $fieldArgsOpeningSymbolPos === false)) {
            //     return [
            //         sprintf(
            //             $this->translationAPI->__('Arguments in field \'%s\' must start with symbol \'%s\' and end with symbol \'%s\', so they have been ignored', 'pop-component-model'),
            //             $fieldArgValue,
            //             QuerySyntax::SYMBOL_FIELDARGS_OPENING,
            //             QuerySyntax::SYMBOL_FIELDARGS_CLOSING
            //         ),
            //     ];
            // }

            // // If the opening bracket is at the beginning, or the closing one is not at the end, it's an error
            // if ($fieldArgsOpeningSymbolPos === 0) {
            //     return [
            //         sprintf(
            //             $this->translationAPI->__('Field name is missing in \'%s\', so it has been ignored', 'pop-component-model'),
            //             $fieldArgValue
            //         ),
            //     ];
            // }
            // if ($fieldArgsClosingSymbolPos !== strlen($fieldArgValue)-1) {
            //     return [
            //         sprintf(
            //             $this->translationAPI->__('Field \'%s\' has arguments, but because the closing argument symbol \'%s\' is not at the end, it has been ignored', 'pop-component-model'),
            //             $fieldArgValue,
            //             QuerySyntax::SYMBOL_FIELDARGS_CLOSING
            //         ),
            //     ];
            // }

            // If it reached here, it's a field! Validate it, or show an error
            return $fieldResolver->resolveSchemaValidationErrorDescriptions($fieldArgValue, $variables);
        }

        return null;
    }

    protected function resolveFieldArgumentValueWarningsForSchema(FieldResolverInterface $fieldResolver, $fieldArgValue, ?array $variables): ?array
    {
        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return GeneralUtils::arrayFlatten(array_filter(array_map(function($fieldArgValueElem) use($fieldResolver, $variables) {
                return $this->resolveFieldArgumentValueWarningsForSchema($fieldResolver, $fieldArgValueElem, $variables);
            }, $fieldArgValue)));
        }

        // If the result fieldArgValue is a field, then validate it and resolve it
        if ($this->isFieldArgumentValueAField($fieldArgValue)) {
            return $fieldResolver->resolveSchemaValidationWarningDescriptions($fieldArgValue, $variables);
        }

        return null;
    }

    protected function resolveFieldArgumentValueDeprecationsForSchema(FieldResolverInterface $fieldResolver, $fieldArgValue, ?array $variables): ?array
    {
        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return GeneralUtils::arrayFlatten(array_filter(array_map(function($fieldArgValueElem) use($fieldResolver, $variables) {
                return $this->resolveFieldArgumentValueDeprecationsForSchema($fieldResolver, $fieldArgValueElem, $variables);
            }, $fieldArgValue)));
        }

        // If the result fieldArgValue is a field, then validate it and resolve it
        if ($this->isFieldArgumentValueAField($fieldArgValue)) {
            return $fieldResolver->getSchemaDeprecationDescriptions($fieldArgValue, $variables);
        }

        return null;
    }
}
