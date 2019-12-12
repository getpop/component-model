<?php
namespace PoP\ComponentModel\TypeResolvers;

use PoP\FieldQuery\QueryUtils;
use PoP\FieldQuery\QuerySyntax;
use PoP\FieldQuery\QueryHelpers;
use PoP\ComponentModel\ErrorUtils;
use PoP\ComponentModel\Environment;
use PoP\FieldQuery\FieldQueryUtils;
use League\Pipeline\PipelineBuilder;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\TypeResolvers\FieldHelpers;
use PoP\ComponentModel\Facades\Engine\DataloadingEngineFacade;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\Facades\Schema\FeedbackMessageStoreFacade;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;
use PoP\ComponentModel\DirectivePipeline\DirectivePipelineDecorator;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups;
use PoP\ComponentModel\Facades\AttachableExtensions\AttachableExtensionManagerFacade;
use PoP\ComponentModel\Feedback\Tokens;

abstract class AbstractTypeResolver implements TypeResolverInterface
{
    public const OPTION_VALIDATE_SCHEMA_ON_RESULT_ITEM = 'validateSchemaOnResultItem';

    /**
     * Cache of which fieldResolvers will process the given field
     *
     * @var array
     */
    protected $fieldResolvers = [];
    protected $schemaDefinition;
    protected $fieldNamesToResolve;
    protected $directiveNameClasses;
    protected $safeVars;

    private $fieldDirectiveIDFields = [];
    private $fieldDirectivesFromFieldCache = [];
    private $dissectedFieldForSchemaCache = [];
    private $directiveResolverInstanceCache = [];

    public function getFieldNamesToResolve(): array
    {
        if (is_null($this->fieldNamesToResolve)) {
            $this->fieldNamesToResolve = $this->calculateFieldNamesToResolve();
        }
        return $this->fieldNamesToResolve;
    }

    public function getSchemaTypeDescription(): ?string
    {
        return null;
    }

    public function getDirectiveNameClasses(): array
    {
        if (is_null($this->directiveNameClasses)) {
            $this->directiveNameClasses = $this->calculateFieldDirectiveNameClasses();
        }
        return $this->directiveNameClasses;
    }

    public function getIdFieldTypeResolverClass(): string
    {
        return get_called_class();
    }

    /**
    * By default, the pipeline must always have directives:
    * 1. Validate: to validate that the schema, fieldNames, etc are supported, and filter them out if not
    * 2. ResolveAndMerge: to resolve the field and place the data into the DB object
    * Additionally to these 2, we can add other mandatory directives, such as:
    * setSelfAsExpression, cacheControl
    * Because it may be more convenient to add the directive or the class, there are 2 methods
    */
    protected function getMandatoryDirectives() {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $dataloadingEngine = DataloadingEngineFacade::getInstance();
        return array_merge(
            array_map(
                function($directiveClass) use($fieldQueryInterpreter) {
                    return $fieldQueryInterpreter->listFieldDirective($directiveClass::getDirectiveName());
                },
                $dataloadingEngine->getMandatoryDirectiveClasses()
            ),
            array_map(
                function($directive) use($fieldQueryInterpreter) {
                    return $fieldQueryInterpreter->listFieldDirective($directive);
                },
                $dataloadingEngine->getMandatoryDirectives()
            )
        );
    }

    /**
     * Validate and resolve the fieldDirectives into an array, each item containing:
     * 1. the directiveResolverInstance
     * 2. its fieldDirective
     * 3. the fields it affects
     *
     * @param array $fieldDirectives
     * @param array $fieldDirectiveFields
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @return array
     */
    public function resolveDirectivesIntoPipelineData(array $fieldDirectives, array &$fieldDirectiveFields, bool $areNestedDirectives, array &$variables, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        /**
        * All directives are placed somewhere in the pipeline. There are 3 positions:
        * 1. At the beginning, before Validate directive
        * 2. In the middle, between the Validate and Resolve directives
        * 3. At the end, after the ResolveAndMerge directive
        */
        $directiveInstancesByPosition = $fieldDirectivesByPosition = $directiveFieldsByPosition = [
            PipelinePositions::FRONT => [],
            PipelinePositions::MIDDLE => [],
            PipelinePositions::BACK => [],
        ];

        // Resolve from directive into their actual object instance.
        $directiveSchemaErrors = $directiveSchemaWarnings = $directiveSchemaDeprecations = [];
        $directiveResolverInstanceData = $this->validateAndResolveInstances($fieldDirectives, $fieldDirectiveFields, $variables, $directiveSchemaErrors, $directiveSchemaWarnings, $directiveSchemaDeprecations);
        // If it is a root directives, then add the fields where they appear into the errors/warnings/deprecations
        if (!$areNestedDirectives) {
            // In the case of an error, Maybe prepend the field(s) containing the directive. Eg: when the directive name doesn't exist:
            // /?query=id<skipanga>
            foreach ($directiveSchemaErrors as $directiveSchemaError) {
                $directive = $directiveSchemaError[Tokens::PATH][0];
                if ($directiveFields = $fieldDirectiveFields[$directive]) {
                    $fields = implode($translationAPI->__(', '), $directiveFields);
                    $schemaErrors[] = [
                        Tokens::PATH => array_merge([$fields], $directiveSchemaError[Tokens::PATH]),
                        Tokens::MESSAGE => $directiveSchemaError[Tokens::MESSAGE],
                    ];
                } else {
                    $schemaErrors[] = $directiveSchemaError;
                }
            }
            foreach ($directiveSchemaWarnings as $directiveSchemaWarning) {
                $directive = $directiveSchemaWarning[Tokens::PATH][0];
                if ($directiveFields = $fieldDirectiveFields[$directive]) {
                    $fields = implode($translationAPI->__(', '), $directiveFields);
                    $schemaWarnings[] = [
                        Tokens::PATH => array_merge([$fields], $directiveSchemaWarning[Tokens::PATH]),
                        Tokens::MESSAGE => $directiveSchemaWarning[Tokens::MESSAGE],
                    ];
                } else {
                    $schemaWarnings[] = $directiveSchemaWarning;
                }
            }
            foreach ($directiveSchemaDeprecations as $directiveSchemaDeprecation) {
                $directive = $directiveSchemaDeprecation[Tokens::PATH][0];
                if ($directiveFields = $fieldDirectiveFields[$directive]) {
                    $fields = implode($translationAPI->__(', '), $directiveFields);
                    $schemaDeprecations[] = [
                        Tokens::PATH => array_merge([$fields], $directiveSchemaDeprecation[Tokens::PATH]),
                        Tokens::MESSAGE => $directiveSchemaDeprecation[Tokens::MESSAGE],
                    ];
                } else {
                    $schemaDeprecations[] = $directiveSchemaDeprecation;
                }
            }
        } else {
            $schemaErrors = array_merge(
                $schemaErrors,
                $directiveSchemaErrors
            );
            $schemaWarnings = array_merge(
                $schemaWarnings,
                $directiveSchemaWarnings
            );
            $schemaDeprecations = array_merge(
                $schemaDeprecations,
                $directiveSchemaDeprecations
            );
        }

        // Create an array with the dataFields affected by each directive, in order in which they will be invoked
        foreach ($directiveResolverInstanceData as $instanceID => $directiveResolverInstanceData) {
            // Add the directive in its required position in the pipeline, and retrieve what fields it will process
            $directiveResolverInstance = $directiveResolverInstanceData['instance'];
            $pipelinePosition = $directiveResolverInstance->getPipelinePosition();
            $directiveInstancesByPosition[$pipelinePosition][] = $directiveResolverInstance;
            $fieldDirectivesByPosition[$pipelinePosition][] = $directiveResolverInstanceData['fieldDirective'];
            $directiveFieldsByPosition[$pipelinePosition][] = $directiveResolverInstanceData['fields'];
        }
        // Once we have them ordered, we can simply discard the positions, keep only the values
        // Each item has 3 elements: the directiveResolverInstance, its fieldDirective, and the fields it affects
        $pipelineData = [];
        foreach ($directiveInstancesByPosition as $position => $directiveResolverInstances) {
            for ($i=0; $i<count($directiveResolverInstances); $i++) {
                $pipelineData[] = [
                    'instance' => $directiveResolverInstances[$i],
                    'fieldDirective' => $fieldDirectivesByPosition[$position][$i],
                    'fields' => $directiveFieldsByPosition[$position][$i],
                ];
            }
        }
        return $pipelineData;
    }

    public function getDirectivePipeline(array $directiveResolverInstances): DirectivePipelineDecorator
    {
        // From the ordered directives, create the pipeline
        $pipelineBuilder = new PipelineBuilder();
        foreach ($directiveResolverInstances as $directiveResolverInstance) {
            $pipelineBuilder->add($directiveResolverInstance);
        }
        $directivePipeline = new DirectivePipelineDecorator($pipelineBuilder->build());
        return $directivePipeline;
    }

    protected function validateAndResolveInstances(array $fieldDirectives, array $fieldDirectiveFields, array &$variables, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();

        // Check if, once a directive fails, the continuing directives must execute or not
        $stopDirectivePipelineExecutionIfDirectiveFailed = Environment::stopDirectivePipelineExecutionIfDirectiveFailed();
        if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
            $stopDirectivePipelineExecutionPlaceholder = $translationAPI->__('Because directive \'%s\' failed, the succeeding directives in the pipeline have not been executed', 'pop-component-model');
        }

        $instances = [];
        // Count how many times each directive is added
        $directiveCount = [];
        $directiveResolverInstanceFields = [];
        for ($i=0; $i<count($fieldDirectives); $i++) {
            // Because directives can be repeated inside a field (eg: <resize(50%),resize(50%)>),
            // then we deal with 2 variables:
            // 1. $fieldDirective: the actual directive
            // 2. $enqueuedFieldDirective: how it was added to the array
            // For retrieving the idsDataFields for the directive, we'll use $enqueuedFieldDirective, since under this entry we stored all the data in the previous functions
            // For everything else, we use $fieldDirective
            $enqueuedFieldDirective = $fieldDirectives[$i];
            // Check if it is a repeated directive: if it has the "|" symbol
            $counterSeparatorPos = QueryUtils::findLastSymbolPosition(
                $enqueuedFieldDirective,
                FieldSymbols::REPEATED_DIRECTIVE_COUNTER_SEPARATOR,
                [QuerySyntax::SYMBOL_FIELDARGS_OPENING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_OPENING],
                [QuerySyntax::SYMBOL_FIELDARGS_CLOSING, QuerySyntax::SYMBOL_FIELDDIRECTIVE_CLOSING],
                QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING,
                QuerySyntax::SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING
            );
            $isRepeatedFieldDirective = $counterSeparatorPos !== false;
            if ($isRepeatedFieldDirective) {
                // Remove the "|counter" bit from the fieldDirective
                $fieldDirective = substr($enqueuedFieldDirective, 0, $counterSeparatorPos);
            } else {
                $fieldDirective = $enqueuedFieldDirective;
            }

            $fieldDirectiveResolverInstances = $this->getDirectiveResolverInstanceForDirective($fieldDirective, $fieldDirectiveFields[$enqueuedFieldDirective], $variables);
            $directiveName = $fieldQueryInterpreter->getFieldDirectiveName($fieldDirective);
            // If there is no directive with this name, show an error and skip it
            if (is_null($fieldDirectiveResolverInstances)) {
                $schemaErrors[] = [
                    Tokens::PATH => [$fieldDirective],
                    Tokens::MESSAGE => sprintf(
                        $translationAPI->__('No DirectiveResolver resolves directive with name \'%s\'', 'pop-component-model'),
                        $directiveName
                    ),
                ];
                if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                    $schemaErrors[] = [
                        Tokens::PATH => [$fieldDirective],
                        Tokens::MESSAGE => sprintf(
                            $stopDirectivePipelineExecutionPlaceholder,
                            $fieldDirective
                        ),
                    ];
                    break;
                }
                continue;
            }
            $directiveArgs = $fieldQueryInterpreter->extractStaticDirectiveArguments($fieldDirective);

            if (empty($fieldDirectiveResolverInstances)) {
                $schemaErrors[] = [
                    Tokens::PATH => [$fieldDirective],
                    Tokens::MESSAGE => sprintf(
                        $translationAPI->__('No DirectiveResolver processes directive with name \'%s\' and arguments \'%s\' in field(s) \'%s\'', 'pop-component-model'),
                        $directiveName,
                        json_encode($directiveArgs),
                        implode(
                            $translationAPI->__('\', \'', 'pop-component-model'),
                            $fieldDirectiveFields[$fieldDirective]
                        )
                    ),
                ];
                if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                    $schemaErrors[] = [
                        Tokens::PATH => [$fieldDirective],
                        Tokens::MESSAGE => sprintf(
                            $stopDirectivePipelineExecutionPlaceholder,
                            $fieldDirective
                        ),
                    ];
                    break;
                }
                continue;
            }

            foreach ($fieldDirectiveFields[$enqueuedFieldDirective] as $field) {
                $directiveResolverInstance = $fieldDirectiveResolverInstances[$field];
                if (is_null($directiveResolverInstance)) {
                    $schemaErrors[] = [
                        Tokens::PATH => [$fieldDirective],
                        Tokens::MESSAGE => sprintf(
                            $translationAPI->__('No DirectiveResolver processes directive with name \'%s\' and arguments \'%s\' in field \'%s\'', 'pop-component-model'),
                            $directiveName,
                            json_encode($directiveArgs),
                            $field
                        ),
                    ];
                    if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                        $schemaErrors[] = [
                            Tokens::PATH => [$fieldDirective],
                            Tokens::MESSAGE => sprintf(
                                $stopDirectivePipelineExecutionPlaceholder,
                                $fieldDirective
                            ),
                        ];
                        break;
                    }
                    continue;
                }

                // Consolidate the same directiveResolverInstances for different fields, as to do the validation only once on each of them
                $instanceID = get_class($directiveResolverInstance).$enqueuedFieldDirective;
                if (!isset($directiveResolverInstanceFields[$instanceID])) {
                    $directiveResolverInstanceFields[$instanceID]['fieldDirective'] = $fieldDirective;
                    $directiveResolverInstanceFields[$instanceID]['enqueuedFieldDirective'] = $enqueuedFieldDirective;
                    $directiveResolverInstanceFields[$instanceID]['instance'] = $directiveResolverInstance;
                }
                $directiveResolverInstanceFields[$instanceID]['fields'][] = $field;
            }
        }

        // Validate all the directiveResolvers in the field
        foreach ($directiveResolverInstanceFields as $instanceID => $instanceData) {
            $fieldDirective = $instanceData['fieldDirective'];
            $enqueuedFieldDirective = $instanceData['enqueuedFieldDirective'];
            $directiveResolverInstance = $instanceData['instance'];
            $directiveResolverFields = $instanceData['fields'];
            // If the enqueued and the fieldDirective are different, it's because it is a repeated one
            $isRepeatedFieldDirective = $fieldDirective != $enqueuedFieldDirective;
            // If it is a repeated directive, no need to do the validation again
            if ($isRepeatedFieldDirective) {
                // If there is an existing error, then skip adding this resolver to the pipeline
                if (!empty(array_filter(
                    $schemaErrors,
                    function($schemaError) use($fieldDirective) {
                        return $schemaError[Tokens::PATH][0] == $fieldDirective;
                    }
                ))) {
                    continue;
                }
            } else {
                // Validate schema (eg of error in schema: ?query=posts<include(if:this-field-doesnt-exist())>)
                $fieldSchemaErrors = [];
                list(
                    $validFieldDirective,
                    $directiveName,
                    $directiveArgs,
                ) = $directiveResolverInstance->dissectAndValidateDirectiveForSchema($this, $fieldDirectiveFields, $variables, $fieldSchemaErrors, $schemaWarnings, $schemaDeprecations);
                if ($fieldSchemaErrors) {
                    $schemaErrors = array_merge(
                        $schemaErrors,
                        $fieldSchemaErrors
                    );
                    // Because there were schema errors, skip this directive
                    if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                        $schemaErrors[] = [
                            Tokens::PATH => [$fieldDirective],
                            Tokens::MESSAGE => sprintf(
                                $stopDirectivePipelineExecutionPlaceholder,
                                $fieldDirective
                            ),
                        ];
                        break;
                    }
                    continue;
                }

                // Validate against the directiveResolver
                if ($maybeError = $directiveResolverInstance->resolveSchemaValidationErrorDescription($this, $directiveName, $directiveArgs)) {
                    $schemaErrors[] = [
                        Tokens::PATH => [$fieldDirective],
                        Tokens::MESSAGE => $maybeError,
                    ];
                    if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                        $schemaErrors[] = [
                            Tokens::PATH => [$fieldDirective],
                            Tokens::MESSAGE => sprintf(
                                $stopDirectivePipelineExecutionPlaceholder,
                                $fieldDirective
                            ),
                        ];
                        break;
                    }
                    continue;
                }

                // Check for deprecations
                if ($deprecationDescription = $directiveResolverInstance->getSchemaDirectiveDeprecationDescription($this)) {
                    $schemaDeprecations[] = [
                        Tokens::PATH => [$fieldDirective],
                        Tokens::MESSAGE => $deprecationDescription,
                    ];
                }
            }

            // Validate if the directive can be executed multiple times
            $directiveName = $fieldQueryInterpreter->getFieldDirectiveName($fieldDirective);
            $directiveCount[$directiveName] = isset($directiveCount[$directiveName]) ? $directiveCount[$directiveName] + 1 : 1;
            if ($directiveCount[$directiveName] > 1 && !$directiveResolverInstance->canExecuteMultipleTimesInField()) {
                $schemaErrors[] = [
                    Tokens::PATH => [$fieldDirective],
                    Tokens::MESSAGE => sprintf(
                        $translationAPI->__('Directive \'%s\' can be executed only once within field \'%s\', so the current execution (number %s) has been ignored', 'pop-component-model'),
                        $fieldDirective,
                        $field,
                        $directiveCount[$directiveName]
                    ),
                ];
                if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                    $schemaErrors[] = [
                        Tokens::PATH => [$fieldDirective],
                        Tokens::MESSAGE => sprintf(
                            $stopDirectivePipelineExecutionPlaceholder,
                            $fieldDirective
                        ),
                    ];
                    break;
                }
                continue;
            }

            // Directive is valid. Add it under its instanceID, which enables to add fields under the same directiveResolverInstance
            $instances[$instanceID]['instance'] = $directiveResolverInstance;
            $instances[$instanceID]['fieldDirective'] = $fieldDirective;
            $instances[$instanceID]['fields'] = $directiveResolverFields;
        }
        return $instances;
    }

    public function getDirectiveResolverInstanceForDirective(string $fieldDirective, array $fieldDirectiveFields, array &$variables): ?array
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $directiveName = $fieldQueryInterpreter->getFieldDirectiveName($fieldDirective);
        $directiveArgs = $fieldQueryInterpreter->extractStaticDirectiveArguments($fieldDirective);

        $directiveNameClasses = $this->getDirectiveNameClasses();
        $directiveClasses = $directiveNameClasses[$directiveName];
        if (is_null($directiveClasses)) {
            return null;
        }

        // Calculate directives per field
        $fieldDirectiveResolverInstances = [];
        foreach ($fieldDirectiveFields as $field) {
            // Check that at least one class which deals with this directiveName can satisfy the directive (for instance, validating that a required directiveArg is present)
            $fieldName = $fieldQueryInterpreter->getFieldName($field);
            foreach ($directiveClasses as $directiveClass) {
                $directiveSupportedFieldNames = $directiveClass::getFieldNamesToApplyTo();
                // If this field is not supported by the directive, skip
                if ($directiveSupportedFieldNames && !in_array($fieldName, $directiveSupportedFieldNames)) {
                    continue;
                }
                // Get the instance from the cache if it exists, or create it if not
                if (is_null($this->directiveResolverInstanceCache[$directiveClass][$fieldDirective])) {
                    $this->directiveResolverInstanceCache[$directiveClass][$fieldDirective] = new $directiveClass($fieldDirective);
                }
                $maybeDirectiveResolverInstance = $this->directiveResolverInstanceCache[$directiveClass][$fieldDirective];
                // Check if this instance can process the directive
                if ($maybeDirectiveResolverInstance->resolveCanProcess($this, $directiveName, $directiveArgs, $field, $variables)) {
                    $fieldDirectiveResolverInstances[$field] = $maybeDirectiveResolverInstance;
                    break;
                }
            }
        }
        return $fieldDirectiveResolverInstances;
    }

    /**
     * By default, do nothing
     *
     * @param string $field
     * @param array $fieldArgs
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @return array
     */
    public function validateFieldArgumentsForSchema(string $field, array $fieldArgs, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        return $fieldArgs;
    }

    protected function getIDsToQuery(array $ids_data_fields)
    {
        return array_keys($ids_data_fields);
    }

    public function fillResultItems(array $ids_data_fields, array &$convertibleDBKeyIDs, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $instanceManager = InstanceManagerFacade::getInstance();

        // Obtain the data for the required object IDs
        $resultIDItems = [];
        $ids = $this->getIDsToQuery($ids_data_fields);
        $typeDataLoaderClass = $this->getTypeDataLoaderClass();
        $typeDataLoader = $instanceManager->getInstance($typeDataLoaderClass);
        foreach ($typeDataLoader->getObjects($ids) as $dataItem) {
            $resultIDItems[$this->getId($dataItem)] = $dataItem;
        }

        // Enqueue the items
        $this->enqueueFillingResultItemsFromIDs($ids_data_fields);

        // Process them
        $this->processFillingResultItemsFromIDs($resultIDItems, $convertibleDBKeyIDs, $dbItems, $previousDBItems, $variables, $messages, $dbErrors, $dbWarnings, $schemaErrors, $schemaWarnings, $schemaDeprecations);
    }

    /**
     * Collect all directives for all fields, and then build a single directive pipeline for all fields,
     * including all directives, even if they don't apply to all fields
     * Eg: id|title<skip>|excerpt<translate> will produce a pipeline [Skip, Translate] where they apply
     * to different fields. After producing the pipeline, add the mandatory items
     *
     * @param array $ids_data_fields
     * @param array $resultIDItems
     * @return void
     */
    public function enqueueFillingResultItemsFromIDs(array $ids_data_fields)
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $mandatoryRootFieldDirectives = implode(
            QuerySyntax::SYMBOL_FIELDDIRECTIVE_SEPARATOR,
            array_map(
                [$fieldQueryInterpreter, 'convertDirectiveToFieldDirective'],
                $this->getMandatoryDirectives()
            )
        );
        $fieldDirectiveCounter = [];
        foreach ($ids_data_fields as $id => $data_fields) {
            $fields = $data_fields['direct'];
            // Watch out: If there are conditional fields, these will be processed by this directive too
            // Hence, collect all these fields, and add them as if they were direct
            $conditionalFields = FieldHelpers::extractConditionalFields($data_fields);
            $fields = array_unique(array_merge(
                $fields,
                $conditionalFields
            ));
            foreach ($fields as $field) {
                if (is_null($this->fieldDirectivesFromFieldCache[$field])) {
                    $fieldDirectives = $fieldQueryInterpreter->getFieldDirectives($field, false) ?? '';
                    // Place the mandatory directives at the beginning of the list, then they will be added to their needed position in the pipeline
                    $fieldDirectives = $fieldDirectives ?
                        $mandatoryRootFieldDirectives.QuerySyntax::SYMBOL_FIELDDIRECTIVE_SEPARATOR.$fieldDirectives :
                        $mandatoryRootFieldDirectives;
                    $this->fieldDirectivesFromFieldCache[$field] = $fieldDirectives;
                }
                // Extract all the directives, and store which fields they process
                foreach (QueryHelpers::splitFieldDirectives($this->fieldDirectivesFromFieldCache[$field]) as $fieldDirective) {
                    // Watch out! Directives can be repeated, and then they must be executed multiple times
                    // Eg: resizing a pic to 25%: <resize(50%),resize(50%)>
                    // However, because we are adding the $idsDataFields under key $fieldDirective, when the 2nd occurrence of the directive is found,
                    // adding data would just override the previous entry, and we can't keep track that it's another iteration
                    // Then, as solution, change the name of the $fieldDirective, adding "|counter". This is an artificial construction,
                    // in which the "|" symbol could not be part of the field naturally
                    if (isset($fieldDirectiveCounter[$field][(string)$id][$fieldDirective])) {
                        // Increase counter and add to $fieldDirective
                        $fieldDirective .= FieldSymbols::REPEATED_DIRECTIVE_COUNTER_SEPARATOR.(++$fieldDirectiveCounter[$field][(string)$id][$fieldDirective]);
                    } else {
                        $fieldDirectiveCounter[$field][(string)$id][$fieldDirective] = 0;
                    }
                    // Store which ID/field this directive must process
                    if (in_array($field, $data_fields['direct'])) {
                        $this->fieldDirectiveIDFields[$fieldDirective][(string)$id]['direct'][] = $field;
                    }
                    if ($conditionalFields = $data_fields['conditional'][$field]) {
                        $this->fieldDirectiveIDFields[$fieldDirective][(string)$id]['conditional'][$field] = array_merge_recursive(
                            $this->fieldDirectiveIDFields[$fieldDirective][(string)$id]['conditional'][$field] ?? [],
                            $conditionalFields
                        );
                    }
                }
            }
        }
    }

    protected function processFillingResultItemsFromIDs(array &$resultIDItems, array &$convertibleDBKeyIDs, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        // Iterate while there are directives with data to be processed
        while (!empty($this->fieldDirectiveIDFields)) {
            $fieldDirectiveIDFields = $this->fieldDirectiveIDFields;
            // Now that we have all data, remove all entries from the inner stack.
            // It may be filled again with nested directives, when resolving the pipeline
            $this->fieldDirectiveIDFields = [];

            // Calculate the fieldDirectives
            $fieldDirectives = array_keys($fieldDirectiveIDFields);

            // Calculate all the fields on which the directive will be applied.
            $fieldDirectiveFields = $fieldDirectiveFieldIDs = [];
            $fieldDirectiveDirectFields = [];
            foreach ($fieldDirectives as $fieldDirective) {
                foreach ($fieldDirectiveIDFields[$fieldDirective] as $id => $dataFields) {
                    $fieldDirectiveDirectFields = array_merge(
                        $fieldDirectiveDirectFields,
                        $dataFields['direct']
                    );
                    $conditionalFields = FieldHelpers::extractConditionalFields($dataFields);
                    $idFieldDirectiveIDFields = array_merge(
                        $dataFields['direct'],
                        $conditionalFields
                    );
                    $fieldDirectiveFields[$fieldDirective] = array_merge(
                        $fieldDirectiveFields[$fieldDirective] ?? [],
                        $idFieldDirectiveIDFields
                    );
                    // Also transpose the array to match field to IDs later on
                    foreach ($idFieldDirectiveIDFields as $field) {
                        $fieldDirectiveFieldIDs[$fieldDirective][$field][] = $id;
                    }
                }
                $fieldDirectiveFields[$fieldDirective] = array_unique($fieldDirectiveFields[$fieldDirective]);
            }
            $fieldDirectiveDirectFields = array_unique($fieldDirectiveDirectFields);
            $idFieldDirectiveIDFields = array_unique($idFieldDirectiveIDFields);

            // Validate and resolve the directives into instances and fields they operate on
            $directivePipelineData = $this->resolveDirectivesIntoPipelineData($fieldDirectives, $fieldDirectiveFields, false, $variables, $schemaErrors, $schemaWarnings, $schemaDeprecations);

            // From the fields, reconstitute the $idsDataFields for each directive, and build the array to pass to the pipeline, for each directive (stage)
            $directiveResolverInstances = $pipelineIDsDataFields = [];
            foreach ($directivePipelineData as $pipelineStageData) {
                $directiveResolverInstance = $pipelineStageData['instance'];
                $fieldDirective = $pipelineStageData['fieldDirective'];
                $directiveFields = $pipelineStageData['fields'];
                // Only process the direct fields
                $directiveDirectFields = array_intersect(
                    $directiveFields,
                    $fieldDirectiveDirectFields
                );
                // From the fields, reconstitute the $idsDataFields for each directive, and build the array to pass to the pipeline, for each directive (stage)
                $directiveIDFields = [];
                foreach ($directiveDirectFields as $field) {
                    $ids = $fieldDirectiveFieldIDs[$fieldDirective][$field];
                    foreach ($ids as $id) {
                        $directiveIDFields[$id]['direct'][] = $field;
                        if ($fieldConditionalFields = $fieldDirectiveIDFields[$fieldDirective][$id]['conditional'][$field]) {
                            $directiveIDFields[$id]['conditional'][$field] = $fieldConditionalFields;
                        } else {
                            $directiveIDFields[$id]['conditional'] = $directiveIDFields[$id]['conditional'] ?? [];
                        }
                    }
                }
                $pipelineIDsDataFields[] = $directiveIDFields;
                $directiveResolverInstances[] = $directiveResolverInstance;
            }

            // We can finally resolve the pipeline, passing along an array with the ID and fields for each directive
            $directivePipeline = $this->getDirectivePipeline($directiveResolverInstances);
            $directivePipeline->resolveDirectivePipeline(
                $this,
                $pipelineIDsDataFields,
                $resultIDItems,
                $convertibleDBKeyIDs,
                $dbItems,
                $previousDBItems,
                $variables,
                $messages,
                $dbErrors,
                $dbWarnings,
                $schemaErrors,
                $schemaWarnings,
                $schemaDeprecations
            );
        }
    }

    protected function dissectFieldForSchema(string $field): ?array
    {
        if (!isset($this->dissectedFieldForSchemaCache[$field])) {
            $this->dissectedFieldForSchemaCache[$field] = $this->doDissectFieldForSchema($field);
        }
        return $this->dissectedFieldForSchemaCache[$field];
    }

    protected function doDissectFieldForSchema(string $field): ?array
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        return $fieldQueryInterpreter->extractFieldArgumentsForSchema($this, $field);
    }

    public function resolveSchemaValidationErrorDescriptions(string $field, array &$variables = null): ?array
    {
        // Get the value from a fieldResolver, from the first one that resolves it
        // $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        // $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);
        if ($fieldResolvers = $this->getFieldResolversForField($field)) {
            list(
                $validField,
                $fieldName,
                $fieldArgs,
                $schemaErrors,
            ) = $this->dissectFieldForSchema($field);
            if ($maybeError = $fieldResolvers[0]->resolveSchemaValidationErrorDescription($this, $fieldName, $fieldArgs)) {
                $schemaErrors[] = [
                    Tokens::PATH => [$field],
                    Tokens::MESSAGE => $maybeError,
                ];
            }
            return $schemaErrors;
        }

        // If we reach here, no fieldResolver processes this field, which is an error
        $translationAPI = TranslationAPIFacade::getInstance();
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $fieldName = $fieldQueryInterpreter->getFieldName($field);
        return [
            [
                Tokens::PATH => [$field],
                Tokens::MESSAGE => sprintf(
                    $translationAPI->__('No FieldResolver resolves field \'%s\'', 'pop-component-model'),
                    $fieldName
                ),
            ],
        ];
    }

    public function resolveSchemaValidationWarningDescriptions(string $field, array &$variables = null): array
    {
        // Get the value from a fieldResolver, from the first one that resolves it
        if ($fieldResolvers = $this->getFieldResolversForField($field)) {
            list(
                $validField,
                $fieldName,
                $fieldArgs,
                $schemaErrors,
                $schemaWarnings,
            ) = $this->dissectFieldForSchema($field);
            if ($maybeWarning = $fieldResolvers[0]->resolveSchemaValidationWarningDescription($this, $fieldName, $fieldArgs)) {
                // $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
                // $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);
                $schemaWarnings[] = [
                    Tokens::PATH => [$field],
                    Tokens::MESSAGE => $maybeWarning,
                ];
            }
            return $schemaWarnings;
        }

        return [];
    }

    public function resolveSchemaDeprecationDescriptions(string $field, array &$variables = null): array
    {
        // Get the value from a fieldResolver, from the first one that resolves it
        if ($fieldResolvers = $this->getFieldResolversForField($field)) {
            list(
                $validField,
                $fieldName,
                $fieldArgs,
                $schemaErrors,
                $schemaWarnings,
                $schemaDeprecations,
            ) = $this->dissectFieldForSchema($field);
            if ($maybeDeprecation = $fieldResolvers[0]->getSchemaFieldDeprecationDescription($this, $fieldName, $fieldArgs)) {
                // $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
                // $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);
                $schemaDeprecations[] = [
                    Tokens::PATH => [$field],
                    Tokens::MESSAGE => $maybeDeprecation,
                ];
            }
            return $schemaDeprecations;
        }

        return [];
    }

    public function getSchemaFieldArgs(string $field): array
    {
        // Get the value from a fieldResolver, from the first one that resolves it
        if ($fieldResolvers = $this->getFieldResolversForField($field)) {
            $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
            $fieldName = $fieldQueryInterpreter->getFieldName($field);
            return $fieldResolvers[0]->getSchemaFieldArgs($this, $fieldName);
        }

        return [];
    }

    public function enableOrderedSchemaFieldArgs(string $field): bool
    {
        // Get the value from a fieldResolver, from the first one that resolves it
        if ($fieldResolvers = $this->getFieldResolversForField($field)) {
            $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
            $fieldName = $fieldQueryInterpreter->getFieldName($field);
            return $fieldResolvers[0]->enableOrderedSchemaFieldArgs($this, $fieldName);
        }

        return false;
    }

    public function resolveFieldTypeResolverClass(string $field): ?string
    {
        // Get the value from a fieldResolver, from the first one that resolves it
        if ($fieldResolvers = $this->getFieldResolversForField($field)) {
            list(
                $validField,
                $fieldName,
                $fieldArgs,
            ) = $this->dissectFieldForSchema($field);
            return $fieldResolvers[0]->resolveFieldTypeResolverClass($this, $fieldName, $fieldArgs);
        }

        return null;
    }

    public function resolveValue($resultItem, string $field, ?array $variables = null, ?array $expressions = null, array $options = [])
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        // Get the value from a fieldResolver, from the first one who can deliver the value
        // (The fact that they resolve the fieldName doesn't mean that they will always resolve it for that specific $resultItem)
        if ($fieldResolvers = $this->getFieldResolversForField($field)) {
            $feedbackMessageStore = FeedbackMessageStoreFacade::getInstance();
            // Important: $validField becomes $field: remove all invalid fieldArgs before executing `resolveValue` on the fieldResolver
            list(
                $field,
                $fieldName,
                $fieldArgs,
                $schemaErrors,
                // $schemaWarnings,
            ) = $this->dissectFieldForSchema($field);

            // // Store the warnings to be read if needed
            // if ($schemaWarnings) {
            //     $feedbackMessageStore->addSchemaWarnings($schemaWarnings);
            // }
            if ($schemaErrors) {
                return ErrorUtils::getNestedSchemaErrorsFieldError($schemaErrors, $fieldName);
            }

            // Important: calculate 'isAnyFieldArgumentValueDynamic' before resolving the args for the resultItem
            // That is because if when resolving there is an error, the fieldArgValue will be removed completely, then we don't know that we must validate the schema again
            // Eg: doing /?query=arrayUnique(extract(..., 0)) and extract fails, arrayUnique will have no fieldArgs. However its fieldArg is mandatory, but by then it doesn't know it needs to validate it
            // Before resolving the fieldArgValues which are fields:
            // Calculate $validateSchemaOnResultItem: if any value containes a field, then we must perform the schemaValidation on the item, such as checking that all mandatory fields are there
            // For instance: After resolving a field and being casted it may be incorrect, so the value is invalidated, and after the schemaValidation the proper error is shown
            // Also need to check for variables, since these must be resolved too
            // For instance: ?query=posts(limit:3),post(id:$id).id|title&id=112
            // We can also force it through an option. This is needed when the field is created on runtime.
            // Eg: through the <transform> directive, in which case no parameter is dynamic anymore by the time it reaches here, yet it was not validated statically either
            $validateSchemaOnResultItem =
                $options[self::OPTION_VALIDATE_SCHEMA_ON_RESULT_ITEM] ||
                FieldQueryUtils::isAnyFieldArgumentValueDynamic(
                    array_values(
                        $fieldQueryInterpreter->extractFieldArguments($this, $field)
                    )
                );

            // Once again, the $validField becomes the $field
            list(
                $field,
                $fieldName,
                $fieldArgs,
                $dbErrors,
                $dbWarnings
            ) = $fieldQueryInterpreter->extractFieldArgumentsForResultItem($this, $resultItem, $field, $variables, $expressions);

            // Store the warnings to be read if needed
            if ($dbWarnings) {
                $feedbackMessageStore->addDBWarnings($dbWarnings);
            }
            if ($dbErrors) {
                return ErrorUtils::getNestedDBErrorsFieldError($dbErrors, $fieldName);
            }

            foreach ($fieldResolvers as $fieldResolver) {
                // Also send the typeResolver along, as to get the id of the $resultItem being passed
                if ($fieldResolver->resolveCanProcessResultItem($this, $resultItem, $fieldName, $fieldArgs)) {
                    if ($validateSchemaOnResultItem) {
                        if ($maybeError = $fieldResolver->resolveSchemaValidationErrorDescription($this, $fieldName, $fieldArgs)) {
                            return ErrorUtils::getValidationFailedError($fieldName, $fieldArgs, $maybeError);
                        }
                    }
                    if ($validationErrorDescription = $fieldResolver->getValidationErrorDescription($this, $resultItem, $fieldName, $fieldArgs)) {
                        return ErrorUtils::getValidationFailedError($fieldName, $fieldArgs, $validationErrorDescription);
                    }
                    return $fieldResolver->resolveValue($this, $resultItem, $fieldName, $fieldArgs, $variables, $expressions, $options);
                }
            }
            return ErrorUtils::getNoFieldResolverProcessesFieldError($this->getId($resultItem), $fieldName, $fieldArgs);
        }

        // Return an error to indicate that no fieldResolver processes this field, which is different than returning a null value.
        // Needed for compatibility with PostConvertibleTypeResolver (so that data-fields aimed for another post_type are not retrieved)
        $fieldName = $fieldQueryInterpreter->getFieldName($field);
        return ErrorUtils::getNoFieldError($fieldName);
    }

    public function getSchemaDefinition(array $stackMessages, array &$generalMessages, array $options = []): array
    {
        $typeName = $this->getTypeName();

        // Stop recursion
        $class = get_called_class();
        if (in_array($class, $stackMessages['processed'])) {
            return [
                $typeName => [
                    SchemaDefinition::ARGNAME_RECURSION => true,
                ]
            ];
        }

        $isFlatShape = $options['shape'] == SchemaDefinition::ARGVALUE_SCHEMA_SHAPE_FLAT;

        // If "compressed" or printing a flat shape, and the resolver has already been added to the schema, then skip it
        if (($isFlatShape || $options['compressed']) && in_array($class, $generalMessages['processed'])) {
            return [
                $typeName => [
                    SchemaDefinition::ARGNAME_REPEATED => true,
                ]
            ];
        }

        $stackMessages['processed'][] = $class;
        $generalMessages['processed'][] = $class;
        if (is_null($this->schemaDefinition)) {
            $this->addSchemaDefinition($stackMessages, $generalMessages, $options);
            // If it is a flat shape, move the nested types to this same level
            if ($isFlatShape) {
                if ($nestedFields = &$this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_FIELDS]) {
                    foreach ($nestedFields as &$nestedField) {
                        if (isset($nestedField[SchemaDefinition::ARGNAME_TYPES])) {
                            $nestedFieldTypes = (array)$nestedField[SchemaDefinition::ARGNAME_TYPES];

                            // Move the type data one level up.
                            // Important: do the merge in this order, because this typeResolver contains its own data, but when it appears within nestedFields, it does not
                            $this->schemaDefinition = array_merge(
                                $nestedFieldTypes,
                                $this->schemaDefinition
                            );

                            // Replace the information with only the names of the types
                            $nestedField[SchemaDefinition::ARGNAME_TYPES] = $nestedField['typeNames'];
                            unset($nestedField['typeNames']);
                        }
                    }
                }
            }
        }

        return $this->schemaDefinition;
    }

    protected function addSchemaDefinition(array $stackMessages, array &$generalMessages, array $options = [])
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $typeName = $this->getTypeName();
        $isFlatShape = $options['shape'] == SchemaDefinition::ARGVALUE_SCHEMA_SHAPE_FLAT;

        // Only in the root we output the operators and helpers
        $isRoot = $stackMessages['is-root'];
        unset($stackMessages['is-root']);

        // Properties
        if ($description = $this->getSchemaTypeDescription()) {
            $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_DESCRIPTION] = $description;
        }

        // Add the directives
        $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_DIRECTIVES] = [];
        $directiveNameClasses = $this->getDirectiveNameClasses();
        foreach ($directiveNameClasses as $directiveName => $directiveClasses) {
            foreach ($directiveClasses as $directiveClass) {
                $directiveResolverInstance = $instanceManager->getInstance($directiveClass);
                // A directive can decide to not be added to the schema, eg: when it is repeated/implemented several times
                if ($directiveResolverInstance->skipAddingToSchemaDefinition()) {
                    continue;
                }
                $isGlobal = $directiveResolverInstance->isGlobal($this);
                if (!$isGlobal || ($isGlobal && $isRoot)) {
                    $directiveSchemaDefinition = $directiveResolverInstance->getSchemaDefinitionForDirective($this);
                    if ($isGlobal) {
                        $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_GLOBAL_DIRECTIVES][] = $directiveSchemaDefinition;
                    } else {
                        $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_DIRECTIVES][] = $directiveSchemaDefinition;
                    }
                }
            }
        }

        // Remove all fields which are not resolved by any unit
        $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_FIELDS] = [];
        $schemaFieldResolvers = $this->calculateAllFieldResolvers();
        foreach ($schemaFieldResolvers as $fieldName => $fieldResolvers) {
            // Get the documentation from the first element
            $fieldResolver = $fieldResolvers[0];
            $isOperatorOrHelper = $fieldResolver->isOperatorOrHelper($this, $fieldName);
            if (!$isOperatorOrHelper || ($isOperatorOrHelper && $isRoot)) {
                // Watch out! We are passing empty $fieldArgs to generate the schema!
                $fieldSchemaDefinition = $fieldResolver->getSchemaDefinitionForField($this, $fieldName, []);
                // Add subfield schema if it is deep, and this typeResolver has not been processed yet
                if ($options['deep']) {
                    // If this field is relational, then add its own schema
                    if ($fieldTypeResolverClass = $this->resolveFieldTypeResolverClass($fieldName)) {
                        $fieldTypeResolver = $instanceManager->getInstance($fieldTypeResolverClass);
                        $fieldSchemaDefinition[SchemaDefinition::ARGNAME_TYPES] = $fieldTypeResolver->getSchemaDefinition($stackMessages, $generalMessages, $options);
                        if ($isFlatShape) {
                            // Store the type names before they are all mixed up in `getSchemaDefinition` when moving them one level up
                            $fieldSchemaDefinition['typeNames'] = [$fieldTypeResolver->getTypeName()];
                        }
                    }
                }
                if ($isOperatorOrHelper) {
                    $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_OPERATORS_AND_HELPERS][] = $fieldSchemaDefinition;
                } else {
                    $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_FIELDS][] = $fieldSchemaDefinition;
                }
            }
        }
    }

    protected function calculateAllFieldResolvers(): array
    {
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();
        $schemaFieldResolvers = [];

        // Iterate classes from the current class towards the parent classes until finding typeResolver that satisfies processing this field
        $class = get_called_class();
        do {
            foreach ($attachableExtensionManager->getExtensionClasses($class, AttachableExtensionGroups::FIELDRESOLVERS) as $extensionClass => $extensionPriority) {
                // Process the fields which have not been processed yet
                foreach (array_diff($extensionClass::getFieldNamesToResolve(), array_keys($schemaFieldResolvers)) as $fieldName) {
                    // Watch out here: no fieldArgs!!!! So this deals with the base case (static), not with all cases (runtime)
                    $schemaFieldResolvers[$fieldName] = $this->getFieldResolversForField($fieldName);
                }
            }
            // Otherwise, continue iterating for the class parents
        } while ($class = get_parent_class($class));

        return $schemaFieldResolvers;
    }

    protected function getFieldResolversForField(string $field): array
    {
        // Calculate the fieldResolver to process this field if not already in the cache
        // If none is found, this value will be set to NULL. This is needed to stop attempting to find the fieldResolver
        if (!isset($this->fieldResolvers[$field])) {
            $this->fieldResolvers[$field] = $this->calculateFieldResolversForField($field);
        }

        return $this->fieldResolvers[$field];
    }

    public function hasFieldResolversForField(string $field): bool
    {
        return !empty($this->getFieldResolversForField($field));
    }

    protected function calculateFieldResolversForField(string $field): array
    {
        // Important: here we CAN'T use `dissectFieldForSchema` to get the fieldArgs, because it will attempt to validate them
        // To validate them, the fieldQueryInterpreter needs to know the schema, so it once again calls functions from this typeResolver
        // Generating an infinite loop
        // Then, just to find out which fieldResolvers will process this field, crudely obtain the fieldArgs, with NO schema-based validation!
        // list(
        //     $field,
        //     $fieldName,
        //     $fieldArgs,
        // ) = $this->dissectFieldForSchema($field);
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $fieldName = $fieldQueryInterpreter->getFieldName($field);
        $fieldArgs = $fieldQueryInterpreter->extractStaticFieldArguments($field);

        $instanceManager = InstanceManagerFacade::getInstance();
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();

        // Iterate classes from the current class towards the parent classes until finding typeResolver that satisfies processing this field
        $class = get_called_class();
        $fieldResolvers = [];
        do {
            // All the Units and their priorities for this class level
            $classTypeResolverPriorities = [];
            $classFieldResolvers = [];

            // Important: do array_reverse to enable more specific hooks, which are initialized later on in the project, to be the chosen ones (if their priority is the same)
            foreach (array_reverse($attachableExtensionManager->getExtensionClasses($class, AttachableExtensionGroups::FIELDRESOLVERS)) as $extensionClass => $extensionPriority) {
                // Check if this fieldResolver can process this field, and if its priority is bigger than the previous found instance attached to the same class
                if (in_array($fieldName, $extensionClass::getFieldNamesToResolve())) {
                    // Check that the fieldResolver can handle the field based on other parameters (eg: "version" in the fieldArgs)
                    $fieldResolver = $instanceManager->getInstance($extensionClass);
                    if ($fieldResolver->resolveCanProcess($this, $fieldName, $fieldArgs)) {
                        $classTypeResolverPriorities[] = $extensionPriority;
                        $classFieldResolvers[] = $fieldResolver;
                    }
                }
            }
            // Sort the found units by their priority, and then add to the stack of all units, for all classes
            // Higher priority means they execute first!
            array_multisort($classTypeResolverPriorities, SORT_DESC, SORT_NUMERIC, $classFieldResolvers);
            $fieldResolvers = array_merge(
                $fieldResolvers,
                $classFieldResolvers
            );
            // Continue iterating for the class parents
        } while ($class = get_parent_class($class));

        // Return all the units that resolve the fieldName
        return $fieldResolvers;
    }

    protected function calculateFieldDirectiveNameClasses(): array
    {
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();

        $directiveNameClasses = [];

        // Iterate classes from the current class towards the parent classes until finding typeResolver that satisfies processing this field
        $class = get_called_class();
        do {
            // Important: do array_reverse to enable more specific hooks, which are initialized later on in the project, to be the chosen ones (if their priority is the same)
            $extensionClassPriorities = array_reverse($attachableExtensionManager->getExtensionClasses($class, AttachableExtensionGroups::DIRECTIVERESOLVERS));
            // Order them by priority: higher priority are evaluated first
            $extensionClasses = array_keys($extensionClassPriorities);
            $extensionPriorities = array_values($extensionClassPriorities);
            array_multisort($extensionPriorities, SORT_DESC, SORT_NUMERIC, $extensionClasses);
            // Add them to the results. We keep the list of all resolvers, so that if the first one cannot process the directive (eg: through `resolveCanProcess`, the next one can do it)
            foreach ($extensionClasses as $extensionClass) {
                $directiveName = $extensionClass::getDirectiveName();
                $directiveNameClasses[$directiveName][] = $extensionClass;
            }
            // Continue iterating for the class parents
        } while ($class = get_parent_class($class));

        return $directiveNameClasses;
    }

    protected function calculateFieldNamesToResolve(): array
    {
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();

        $ret = [];

        // Iterate classes from the current class towards the parent classes until finding typeResolver that satisfies processing this field
        $class = get_called_class();
        do {
            foreach ($attachableExtensionManager->getExtensionClasses($class, AttachableExtensionGroups::FIELDRESOLVERS) as $extensionClass => $extensionPriority) {
                $ret = array_merge(
                    $ret,
                    $extensionClass::getFieldNamesToResolve()
                );
            }
            // Continue iterating for the class parents
        } while ($class = get_parent_class($class));

        return array_values(array_unique($ret));
    }
}
