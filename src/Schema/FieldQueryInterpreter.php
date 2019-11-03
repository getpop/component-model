<?php
namespace PoP\ComponentModel\Schema;
use PoP\FieldQuery\QueryUtils;
use PoP\FieldQuery\QuerySyntax;
use PoP\FieldQuery\QueryHelpers;
use PoP\FieldQuery\FieldQueryUtils;
use PoP\ComponentModel\GeneralUtils;
use PoP\QueryParsing\QueryParserInterface;
use PoP\Translation\TranslationAPIInterface;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\DirectiveResolvers\DirectiveResolverInterface;

class FieldQueryInterpreter extends \PoP\FieldQuery\FieldQueryInterpreter implements FieldQueryInterpreterInterface
{
    // Cache the output from functions
    private $extractedStaticFieldArgumentsCache = [];
    private $extractedStaticDirectiveArgumentsCache = [];
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
     *
     * @param FieldResolverInterface $fieldResolver
     * @param string $field
     * @return array
     */
    public function extractStaticDirectiveArguments(string $directive, ?array $variables = null): array
    {
        return $this->extractStaticFieldArguments($directive, $variables);
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
        if (!isset($this->extractedStaticFieldArgumentsCache[$field])) {
            $this->extractedStaticFieldArgumentsCache[$field] = $this->doExtractStaticFieldArguments($field, $variables);
        }
        return $this->extractedStaticFieldArgumentsCache[$field];
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
                    $separatorPos = QueryUtils::findFirstSymbolPosition($fieldArg, QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
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
        if (!isset($this->extractedDirectiveArgumentsCache[get_class($fieldResolver)][$fieldDirective])) {
            $fieldSchemaWarnings = [];
            $this->extractedDirectiveArgumentsCache[get_class($fieldResolver)][$fieldDirective] = $this->doExtractDirectiveArguments($directiveResolver, $fieldResolver, $fieldDirective, $variables, $fieldSchemaWarnings);
            // Also cache the schemaWarnings
            if (!is_null($schemaWarnings)) {
                $this->extractedDirectiveArgumentWarningsCache[get_class($fieldResolver)][$fieldDirective] = $fieldSchemaWarnings;
            }
        }
        // Integrate the schemaWarnings too
        if (!is_null($schemaWarnings)) {
            $schemaWarnings = array_merge(
                $schemaWarnings,
                $this->extractedDirectiveArgumentWarningsCache[get_class($fieldResolver)][$fieldDirective]
            );
        }
        return $this->extractedDirectiveArgumentsCache[get_class($fieldResolver)][$fieldDirective];
    }

    protected function doExtractDirectiveArguments(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $fieldDirective, ?array $variables, array &$schemaWarnings): array
    {
        $directiveArgs = [];
        // Extract the args from the string into an array
        $directiveArgsStr = $this->getFieldDirectiveArgs($fieldDirective);
        // Remove the opening and closing brackets
        $directiveArgsStr = substr($directiveArgsStr, strlen(QuerySyntax::SYMBOL_FIELDARGS_OPENING), strlen($directiveArgsStr)-strlen(QuerySyntax::SYMBOL_FIELDARGS_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING));
        // Remove the white spaces before and after
        if ($directiveArgsStr = trim($directiveArgsStr)) {
            // Iterate all the elements, and extract them into the array
            if ($directiveArgElems = $this->queryParser->splitElements($directiveArgsStr, QuerySyntax::SYMBOL_FIELDARGS_ARGSEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING)) {
                $directiveArgumentNameTypes = $this->getDirectiveArgumentNameTypes($directiveResolver, $fieldResolver, $fieldDirective);
                $orderedDirectiveArgNamesEnabled = $directiveResolver->enableOrderedSchemaDirectiveArgs($fieldResolver);
                if ($orderedDirectiveArgNamesEnabled) {
                    $orderedDirectiveArgNames = array_keys($directiveArgumentNameTypes);
                }
                for ($i=0; $i<count($directiveArgElems); $i++) {
                    $directiveArg = $directiveArgElems[$i];
                    // Either one of 2 formats are accepted:
                    // 1. The key:value pair
                    // 2. Only the value, and extract the key from the schema definition (if enabled for that directive)
                    $separatorPos = QueryUtils::findFirstSymbolPosition($directiveArg, QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
                    if ($separatorPos === false) {
                        $directiveArgValue = $directiveArg;
                        if (!$orderedDirectiveArgNamesEnabled || !isset($orderedDirectiveArgNames[$i])) {
                            $errorMessage = $orderedDirectiveArgNamesEnabled ?
                                $this->translationAPI->__('documentation for this argument in the schema definition has not been defined, hence it can\'t be deduced from there', 'pop-component-model') :
                                $this->translationAPI->__('retrieving this information from the schema definition is disabled for the corresponding “fieldResolver”', 'pop-component-model');
                            $schemaWarnings[] = sprintf(
                                $this->translationAPI->__('The argument on position number %s (with value \'%s\') has its name missing, and %s. Please define the query using the \'key%svalue\' format. This argument has been ignored', 'pop-component-model'),
                                $i+1,
                                $directiveArgValue,
                                $errorMessage,
                                QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR
                            );
                            // Ignore extracting this argument
                            continue;
                        }
                        $directiveArgName = $orderedDirectiveArgNames[$i];
                        // Log the found directiveArgName
                        $this->feedbackMessageStore->addLogEntry(
                            sprintf(
                                $this->translationAPI->__('In directive \'%s\', the argument on position number %s (with value \'%s\') is resolved as argument \'%s\'', 'pop-component-model'),
                                $fieldDirective,
                                $i+1,
                                $directiveArgValue,
                                $directiveArgName
                            )
                        );
                    } else {
                        $directiveArgName = trim(substr($directiveArg, 0, $separatorPos));
                        $directiveArgValue = trim(substr($directiveArg, $separatorPos + strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR)));
                        // Validate that this argument exists in the schema, or show a warning if not
                        // But don't skip it! It may be that the engine accepts the property, it is just not documented!
                        if (!array_key_exists($directiveArgName, $directiveArgumentNameTypes)) {
                            $schemaWarnings[] = sprintf(
                                $this->translationAPI->__('Argument with name \'%s\' has not been documented in the schema, so it may have no effect (it has not been removed from the query, though)', 'pop-component-model'),
                                $directiveArgName
                            );
                        }
                    }

                    // If the field is an array in its string representation, convert it to array
                    $directiveArgValue = $this->maybeConvertFieldArgumentValue($directiveArgValue, $variables);
                    $directiveArgs[$directiveArgName] = $directiveArgValue;
                }
            }
        }

        return $directiveArgs;
    }

    public function extractFieldArguments(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null, ?array &$schemaWarnings = null): array
    {
        if (!isset($this->extractedFieldArgumentsCache[get_class($fieldResolver)][$field])) {
            $fieldSchemaWarnings = [];
            $this->extractedFieldArgumentsCache[get_class($fieldResolver)][$field] = $this->doExtractFieldArguments($fieldResolver, $field, $variables, $fieldSchemaWarnings);
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

    protected function doExtractFieldArguments(FieldResolverInterface $fieldResolver, string $field, ?array $variables, array &$schemaWarnings): array
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
                $fieldArgumentNameTypes = $this->getFieldArgumentNameTypes($fieldResolver, $field);
                $orderedFieldArgNamesEnabled = $fieldResolver->enableOrderedSchemaFieldArgs($field);
                if ($orderedFieldArgNamesEnabled) {
                    $orderedFieldArgNames = array_keys($fieldArgumentNameTypes);
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
                            // Ignore extracting this argument
                            continue;
                        }
                        $fieldArgName = $orderedFieldArgNames[$i];
                        // Log the found fieldArgName
                        $this->feedbackMessageStore->addLogEntry(
                            sprintf(
                                $this->translationAPI->__('In query field \'%s\', the argument on position number %s (with value \'%s\') is resolved as argument \'%s\'', 'pop-component-model'),
                                $field,
                                $i+1,
                                $fieldArgValue,
                                $fieldArgName
                            )
                        );
                    } else {
                        $fieldArgName = trim(substr($fieldArg, 0, $separatorPos));
                        $fieldArgValue = trim(substr($fieldArg, $separatorPos + strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR)));
                        // Validate that this argument exists in the schema, or show a warning if not
                        // But don't skip it! It may be that the engine accepts the property, it is just not documented!
                        if (!array_key_exists($fieldArgName, $fieldArgumentNameTypes)) {
                            $schemaWarnings[] = sprintf(
                                $this->translationAPI->__('Argument with name \'%s\' has not been documented in the schema, so it may have no effect (it has not been removed from the query, though)', 'pop-component-model'),
                                $fieldArgName
                            );
                        }
                    }

                    // If the field is an array in its string representation, convert it to array
                    $fieldArgValue = $this->maybeConvertFieldArgumentValue($fieldArgValue, $variables);
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
        $extractedFieldArgs = $fieldArgs = $this->extractFieldArguments($fieldResolver, $field, $variables, $schemaWarnings);
        if ($fieldArgs) {
            foreach ($fieldArgs as $fieldArgName => $fieldArgValue) {
                $fieldArgs[$fieldArgName] = $fieldArgValue;
                // Validate it
                if ($maybeErrors = $this->resolveFieldArgumentValueErrorDescriptionsForSchema($fieldResolver, $fieldArgValue, $variables)) {
                    $schemaErrors = array_merge(
                        $schemaErrors,
                        $maybeErrors
                    );
                    // Because it's an error, set the value to null, so it will be filtered out
                    $fieldArgs[$fieldArgName] = null;
                }
                // Find warnings and deprecations
                if ($maybeWarnings = $this->resolveFieldArgumentValueWarningsForSchema($fieldResolver, $fieldArgValue, $variables)) {
                    $schemaWarnings = array_merge(
                        $schemaWarnings,
                        $maybeWarnings
                    );
                }
                if ($maybeDeprecations = $this->resolveFieldArgumentValueDeprecationsForSchema($fieldResolver, $fieldArgValue, $variables)) {
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

    public function extractDirectiveArgumentsForSchema(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $fieldDirective, ?array $variables = null): array
    {
        $schemaErrors = [];
        $schemaWarnings = [];
        $schemaDeprecations = [];
        $validAndResolvedDirective = $fieldDirective;
        $directiveName = $this->getFieldDirectiveName($fieldDirective);
        $extractedDirectiveArgs = $directiveArgs = $this->extractDirectiveArguments($directiveResolver, $fieldResolver, $fieldDirective, $variables, $schemaWarnings);
        if ($directiveArgs) {
            foreach ($directiveArgs as $directiveArgName => $directiveArgValue) {
                $directiveArgs[$directiveArgName] = $directiveArgValue;
                // Validate it
                if ($maybeErrors = $this->resolveFieldArgumentValueErrorDescriptionsForSchema($fieldResolver, $directiveArgValue, $variables)) {
                    $schemaErrors = array_merge(
                        $schemaErrors,
                        $maybeErrors
                    );
                    // Because it's an error, set the value to null, so it will be filtered out
                    $directiveArgs[$directiveArgName] = null;
                }
                // Find warnings and deprecations
                if ($maybeWarnings = $this->resolveFieldArgumentValueWarningsForSchema($fieldResolver, $directiveArgValue, $variables)) {
                    $schemaWarnings = array_merge(
                        $schemaWarnings,
                        $maybeWarnings
                    );
                }
                if ($maybeDeprecations = $this->resolveFieldArgumentValueDeprecationsForSchema($fieldResolver, $directiveArgValue, $variables)) {
                    $schemaDeprecations = array_merge(
                        $schemaDeprecations,
                        $maybeDeprecations
                    );
                }
            }
            $directiveArgs = $this->filterFieldArgs($directiveArgs);
            // Cast the values to their appropriate type. If casting fails, the value returns as null
            $directiveArgs = $this->castAndValidateDirectiveArgumentsForSchema($directiveResolver, $fieldResolver, $fieldDirective, $directiveArgs, $schemaWarnings);
        }
        // If there's an error, those args will be removed. Then, re-create the fieldDirective to pass it to the function below
        if ($schemaErrors) {
            $validAndResolvedDirective = null;
        } else {
            // There are 2 reasons why the fieldDirective might have changed:
            // 1. validField: There are $schemaWarnings: remove the directiveArgs that failed
            // 2. resolvedField: Some directiveArg was a variable: replace it with its value
            if ($extractedDirectiveArgs != $directiveArgs) {
                $validAndResolvedDirective = $this->replaceFieldArgs($fieldDirective, $directiveArgs);
            }
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

    // public function extractDirectiveArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null): array
    // {
    //     return $this->extractFieldArgumentsForSchema($fieldResolver, $field, $variables);
    // }

    public function extractFieldArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $field, ?array $variables = null): array
    {
        $dbErrors = $dbWarnings = [];
        $validAndResolvedField = $field;
        $fieldName = $this->getFieldName($field);
        $extractedFieldArgs = $fieldArgs = $this->extractFieldArguments($fieldResolver, $field, $variables);
        // Only need to extract arguments if they have fields or arrays
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
        }
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
        if (FieldQueryUtils::isAnyFieldArgumentValueAField(
            array_values(
                $directiveArgs
            )
        )) {
            $fieldOutputKey = $fieldDirective;
            $id = $fieldResolver->getId($resultItem);
            foreach ($directiveArgs as $directiveArgName => $directiveArgValue) {
                $directiveArgValue = $this->maybeResolveFieldArgumentValueForResultItem($fieldResolver, $resultItem, $directiveArgValue, $variables);
                // Validate it
                if (\PoP\ComponentModel\GeneralUtils::isError($directiveArgValue)) {
                    $error = $directiveArgValue;
                    if ($errorData = $error->getErrorData()) {
                        $errorOutputKey = $errorData['fieldName'];
                    }
                    $errorOutputKey = $errorOutputKey ?? $fieldOutputKey;
                    $dbErrors[(string)$id][$errorOutputKey][] = $error->getErrorMessage();
                    $directiveArgs[$directiveArgName] = null;
                    continue;
                }
                $directiveArgs[$directiveArgName] = $directiveArgValue;
            }
            $directiveArgs = $this->filterFieldArgs($directiveArgs);
        }
        // Cast the values to their appropriate type. If casting fails, the value returns as null
        $resultItemDBWarnings = [];
        $directiveArgs = $this->castAndValidateDirectiveArgumentsForResultItem($directiveResolver, $fieldResolver, $fieldDirective, $directiveArgs, $resultItemDBWarnings);
        foreach ($resultItemDBWarnings as $warning) {
            $dbWarnings[(string)$id][$fieldOutputKey][] = $warning;
        }
        if ($dbErrors) {
            $validAndResolvedDirective = null;
        } else {
            // There are 2 reasons why the fieldDirective might have changed:
            // 1. validField: There are $dbWarnings: remove the directiveArgs that failed
            // 2. resolvedField: Some directiveArg was a variable: replace it with its value
            if ($extractedDirectiveArgs != $directiveArgs) {
                $validAndResolvedDirective = $this->replaceFieldArgs($fieldDirective, $directiveArgs);
            }
        }
        return [
            $validAndResolvedDirective,
            $directiveName,
            $directiveArgs,
            $dbErrors,
            $dbWarnings
        ];
    }

    protected function castDirectiveArguments(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $directive, array $directiveArgs, array &$failedCastingFieldArgErrorMessages, bool $forSchema): array
    {
        // Get the field argument types, to know to what type it will cast the value
        if ($directiveArgNameTypes = $this->getDirectiveArgumentNameTypes($directiveResolver, $fieldResolver)) {
            // Cast all argument values
            foreach ($directiveArgs as $directiveArgName => $directiveArgValue) {
                // Maybe cast the value to the appropriate type. Eg: from string to boolean
                if ($directiveArgType = $directiveArgNameTypes[$directiveArgName]) {
                    // There are 2 possibilities for casting:
                    // 1. $forSchema = true: Cast all items except fields (eg: has-comments())
                    // 2. $forSchema = false: Should be cast only fields, however by now we can't tell which are fields and which are not, since fields have already been resolved to their value. Hence, cast everything (directiveArgValues that failed at the schema level will not be provided in the input array, so won't be validated twice)
                    // Otherwise, simply add the directiveArgValue directly, it will be eventually casted by the other function
                    if (
                        ($forSchema && !$this->isFieldArgumentValueAField($directiveArgValue)) ||
                        !$forSchema
                    ) {
                        $directiveArgValue = $this->typeCastingExecuter->cast($directiveArgType, $directiveArgValue);
                        // If the response is an error, extract the error message and set value to null
                        if (GeneralUtils::isError($directiveArgValue)) {
                            $error = $directiveArgValue;
                            $failedCastingFieldArgErrorMessages[$directiveArgName] = $error->getErrorMessage();
                            $directiveArgs[$directiveArgName] = null;
                            continue;
                        }
                    }
                    $directiveArgs[$directiveArgName] = $directiveArgValue;
                }
            }
        }
        return $directiveArgs;
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
        if ($directiveDocumentationArgs = $directiveResolver->getSchemaDirectiveArgs($fieldResolver)) {
            foreach ($directiveDocumentationArgs as $directiveDocumentationArg) {
                $directiveArgNameTypes[$directiveDocumentationArg['name']] = $directiveDocumentationArg['type'];
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
        if ($fieldDocumentationArgs = $fieldResolver->getSchemaFieldArgs($field)) {
            foreach ($fieldDocumentationArgs as $fieldDocumentationArg) {
                $fieldArgNameTypes[$fieldDocumentationArg['name']] = $fieldDocumentationArg['type'];
            }
        }
        return $fieldArgNameTypes;
    }

    protected function castAndValidateDirectiveArgumentsForSchema(DirectiveResolverInterface $directiveResolver, FieldResolverInterface $fieldResolver, string $fieldDirective, array $directiveArgs, array &$schemaWarnings): array
    {
        $failedCastingDirectiveArgErrorMessages = [];
        $castedDirectiveArgs = $this->castDirectiveArgumentsForSchema($directiveResolver, $fieldResolver, $fieldDirective, $directiveArgs, $failedCastingDirectiveArgErrorMessages);
        return $this->castAndValidateDirectiveArguments($directiveResolver, $fieldResolver, $castedDirectiveArgs, $failedCastingDirectiveArgErrorMessages, $fieldDirective, $directiveArgs, $schemaWarnings);
    }

    protected function castAndValidateFieldArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, array $fieldArgs, array &$schemaWarnings): array
    {
        $failedCastingFieldArgErrorMessages = [];
        $castedFieldArgs = $this->castFieldArgumentsForSchema($fieldResolver, $field, $fieldArgs, $failedCastingFieldArgErrorMessages);
        return $this->castAndValidateFieldArguments($fieldResolver, $castedFieldArgs, $failedCastingFieldArgErrorMessages, $field, $fieldArgs, $schemaWarnings);
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
        if ($this->isFieldArgumentValueAnArray($fieldArgValue)) {
            $arrayValue = substr($fieldArgValue, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING), strlen($fieldArgValue)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING));
            // Elements are split by ";"
            return $this->queryParser->splitElements($arrayValue, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_SEPARATOR, [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING], [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING], QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING, QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING);
        }

        return $fieldArgValue;
    }

    protected function maybeConvertFieldArgumentArrayValue(string $fieldArgValue, ?array $variables)
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
    protected function maybeResolveFieldArgumentValueForResultItem(FieldResolverInterface $fieldResolver, $resultItem, $fieldArgValue, ?array $variables)
    {
        // If it is an array, apply this function on all elements
        if (is_array($fieldArgValue)) {
            return array_map(function($fieldArgValueElem) use($fieldResolver, $resultItem, $variables) {
                return $this->maybeResolveFieldArgumentValueForResultItem($fieldResolver, $resultItem, $fieldArgValueElem, $variables);
            }, (array)$fieldArgValue);
        }

        // Convert field, remove quotes from strings
        if (!empty($fieldArgValue) && is_string($fieldArgValue)) {
            // If the result fieldArgValue is a string (i.e. not numeric), and it has brackets (...),
            // then it is a field. Validate it and resolve it
            if (
                substr($fieldArgValue, -1*strlen(QuerySyntax::SYMBOL_FIELDARGS_CLOSING)) == QuerySyntax::SYMBOL_FIELDARGS_CLOSING &&
                // Please notice: if position is 0 (i.e. for a string "(something)") then it's not a field, since the fieldName is missing
                // Then it's ok asking for strpos: either `false` or `0` must both fail
                strpos($fieldArgValue, QuerySyntax::SYMBOL_FIELDARGS_OPENING)
            ) {
                return $fieldResolver->resolveValue($resultItem, (string)$fieldArgValue);
            }
            // If it has quotes at the beginning and end, it's a string. Remove them
            if (
                substr($fieldArgValue, 0, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING &&
                substr($fieldArgValue, -1*strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING)) == QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING
            ) {
                return substr($fieldArgValue, strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING), strlen($fieldArgValue)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING)-strlen(QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING));
            }
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
            return $fieldResolver->resolveSchemaValidationWarningDescriptions($fieldArgValue);
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
            return $fieldResolver->getSchemaDeprecationDescriptions($fieldArgValue);
        }

        return null;
    }
}
