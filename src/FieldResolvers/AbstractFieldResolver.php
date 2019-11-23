<?php
namespace PoP\ComponentModel\FieldResolvers;
use PoP\FieldQuery\QueryUtils;
use PoP\FieldQuery\QuerySyntax;
use PoP\FieldQuery\QueryHelpers;
use PoP\ComponentModel\ErrorUtils;
use PoP\ComponentModel\Environment;
use PoP\FieldQuery\FieldQueryUtils;
use League\Pipeline\PipelineBuilder;
use PoP\ComponentModel\DataloaderInterface;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\FieldHelpers;
use PoP\ComponentModel\Facades\Engine\DataloadingEngineFacade;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\Facades\Schema\FeedbackMessageStoreFacade;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;
use PoP\ComponentModel\DirectivePipeline\DirectivePipelineDecorator;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups;
use PoP\ComponentModel\Facades\AttachableExtensions\AttachableExtensionManagerFacade;

abstract class AbstractFieldResolver implements FieldResolverInterface
{
    public const OPTION_VALIDATE_SCHEMA_ON_RESULT_ITEM = 'validateSchemaOnResultItem';
    protected const REPEATED_DIRECTIVE_COUNTER_SEPARATOR = '|';
    /**
     * Cache of which fieldValueResolvers will process the given field
     *
     * @var array
     */
    protected $fieldValueResolvers = [];
    protected $schemaDefinition;
    protected $fieldNamesToResolve;
    protected $directiveNameClasses;
    protected $safeVars;

    private $fieldDirectiveIDFields = [];
    private $fieldDirectiveCounter = [];
    private $fieldDirectivesFromFieldCache = [];
    private $dissectedFieldForSchemaCache = [];
    private $fieldResolverSchemaIdsCache = [];
    private $directiveResolverInstanceCache = [];

    public function getFieldNamesToResolve(): array
    {
        if (is_null($this->fieldNamesToResolve)) {
            $this->fieldNamesToResolve = $this->calculateFieldNamesToResolve();
        }
        return $this->fieldNamesToResolve;
    }

    public function getDirectiveNameClasses(): array
    {
        if (is_null($this->directiveNameClasses)) {
            $this->directiveNameClasses = $this->calculateFieldDirectiveNameClasses();
        }
        return $this->directiveNameClasses;
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
    public function resolveDirectivesIntoPipelineData(array $fieldDirectives, array &$fieldDirectiveFields, array &$variables, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
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
        $directiveResolverInstanceData = $this->validateAndResolveInstances($fieldDirectives, $fieldDirectiveFields, $variables, $schemaErrors, $schemaWarnings, $schemaDeprecations);
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
                self::REPEATED_DIRECTIVE_COUNTER_SEPARATOR,
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
                $schemaErrors[$fieldDirective][] = sprintf(
                    $translationAPI->__('No DirectiveResolver resolves directive with name \'%s\'', 'pop-component-model'),
                    $directiveName
                );
                if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                    $schemaErrors[$fieldDirective][] = sprintf(
                        $stopDirectivePipelineExecutionPlaceholder,
                        $fieldDirective
                    );
                    break;
                }
                continue;
            }
            $directiveArgs = $fieldQueryInterpreter->extractStaticDirectiveArguments($fieldDirective);

            if (empty($fieldDirectiveResolverInstances)) {
                $schemaErrors[$fieldDirective][] = sprintf(
                    $translationAPI->__('No DirectiveResolver processes directive with name \'%s\' and arguments \'%s\' in field(s) \'%s\'', 'pop-component-model'),
                    $directiveName,
                    json_encode($directiveArgs),
                    implode(
                        $translationAPI->__('\', \'', 'pop-component-model'),
                        $fieldDirectiveFields[$fieldDirective]
                    )
                );
                if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                    $schemaErrors[$fieldDirective][] = sprintf(
                        $stopDirectivePipelineExecutionPlaceholder,
                        $fieldDirective
                    );
                    break;
                }
                continue;
            }

            foreach ($fieldDirectiveFields[$enqueuedFieldDirective] as $field) {
                $directiveResolverInstance = $fieldDirectiveResolverInstances[$field];
                if (is_null($directiveResolverInstance)) {
                    $schemaErrors[$fieldDirective][] = sprintf(
                        $translationAPI->__('No DirectiveResolver processes directive with name \'%s\' and arguments \'%s\' in field \'%s\'', 'pop-component-model'),
                        $directiveName,
                        json_encode($directiveArgs),
                        $field
                    );
                    if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                        $schemaErrors[$fieldDirective][] = sprintf(
                            $stopDirectivePipelineExecutionPlaceholder,
                            $fieldDirective
                        );
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
                if (!empty($schemaErrors[$fieldDirective])) {
                    continue;
                }
            } else {
                // Validate schema (eg of error in schema: ?query=posts<include(if:this-field-doesnt-exist())>)
                $fieldSchemaErrors = $fieldSchemaWarnings = $fieldSchemaDeprecations = [];
                list(
                    $validFieldDirective,
                    $directiveName,
                    $directiveArgs,
                ) = $directiveResolverInstance->dissectAndValidateDirectiveForSchema($this, $fieldDirectiveFields, $variables, $fieldSchemaErrors, $fieldSchemaWarnings, $fieldSchemaDeprecations);
                // For each error/warning/deprecation, add the field to provide a better message
                foreach ($fieldSchemaDeprecations as $deprecationFieldDirective => $deprecations) {
                    foreach ($deprecations as $deprecation) {
                        $schemaDeprecations[$deprecationFieldDirective][] = sprintf(
                            $translationAPI->__('In field(s) \'%s\' and directive \'%s\': %s', 'pop-component-model'),
                            implode(
                                $translationAPI->__('\', \'', 'pop-component-model'),
                                $directiveResolverFields
                            ),
                            $fieldDirective,
                            $deprecation
                        );
                    }
                }
                foreach ($fieldSchemaWarnings as $warningFieldDirective => $warnings) {
                    foreach ($warnings as $warning) {
                        $schemaWarnings[$warningFieldDirective][] = sprintf(
                            $translationAPI->__('In field(s) \'%s\' and directive \'%s\': %s', 'pop-component-model'),
                            implode(
                                $translationAPI->__('\', \'', 'pop-component-model'),
                                $directiveResolverFields
                            ),
                            $fieldDirective,
                            $warning
                        );
                    }
                }
                if ($fieldSchemaErrors) {
                    foreach ($fieldSchemaErrors as $errorFieldDirective => $errors) {
                        foreach ($errors as $error) {
                            $schemaErrors[$errorFieldDirective][] = sprintf(
                                $translationAPI->__('In field(s) \'%s\' and directive \'%s\': %s', 'pop-component-model'),
                                implode(
                                    $translationAPI->__('\', \'', 'pop-component-model'),
                                    $directiveResolverFields
                                ),
                                $fieldDirective,
                                $error
                            );
                        }
                    }
                    // Because there were schema errors, skip this directive
                    if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                        $schemaErrors[$fieldDirective][] = sprintf(
                            $stopDirectivePipelineExecutionPlaceholder,
                            $fieldDirective
                        );
                        break;
                    }
                    continue;
                }

                // Validate against the directiveResolver
                if ($maybeError = $directiveResolverInstance->resolveSchemaValidationErrorDescription($this, $directiveName, $directiveArgs)) {
                    $schemaErrors[$fieldDirective][] = sprintf(
                        $translationAPI->__('In field(s) \'%s\' and directive \'%s\': %s', 'pop-component-model'),
                        implode(
                            $translationAPI->__('\', \'', 'pop-component-model'),
                            $directiveResolverFields
                        ),
                        $fieldDirective,
                        $maybeError
                    );
                    if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                        $schemaErrors[$fieldDirective][] = sprintf(
                            $stopDirectivePipelineExecutionPlaceholder,
                            $fieldDirective
                        );
                        break;
                    }
                    continue;
                }

                // Check for deprecations
                if ($deprecationDescription = $directiveResolverInstance->getSchemaDirectiveDeprecationDescription($this)) {
                    $schemaDeprecations[$fieldDirective][] = $deprecationDescription;
                }
            }

            // Validate if the directive can be executed multiple times
            $directiveName = $fieldQueryInterpreter->getFieldDirectiveName($fieldDirective);
            $directiveCount[$directiveName] = isset($directiveCount[$directiveName]) ? $directiveCount[$directiveName] + 1 : 1;
            if ($directiveCount[$directiveName] > 1 && !$directiveResolverInstance->canExecuteMultipleTimesInField()) {
                $schemaErrors[$fieldDirective][] = sprintf(
                    $translationAPI->__('Directive \'%s\' can be executed only once within field \'%s\', so the current execution (number %s) has been ignored', 'pop-component-model'),
                    $fieldDirective,
                    $field,
                    $directiveCount[$directiveName]
                );
                if ($stopDirectivePipelineExecutionIfDirectiveFailed) {
                    $schemaErrors[$fieldDirective][] = sprintf(
                        $stopDirectivePipelineExecutionPlaceholder,
                        $fieldDirective
                    );
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
                // Get the instance from the cache if it exists, or create it if not
                if (is_null($this->directiveResolverInstanceCache[$directiveClass][$fieldDirective])) {
                    $this->directiveResolverInstanceCache[$directiveClass][$fieldDirective] = new $directiveClass($fieldDirective);
                }
                $maybeDirectiveResolverInstance = $this->directiveResolverInstanceCache[$directiveClass][$fieldDirective];
                $directiveSupportedFieldNames = $maybeDirectiveResolverInstance->getFieldNamesToApplyTo();
                if (
                    // Check if this field is supported by the directive
                    (!$directiveSupportedFieldNames || in_array($fieldName, $directiveSupportedFieldNames)) &&
                    // Check if this instance can process the directive
                    $maybeDirectiveResolverInstance->resolveCanProcess($this, $directiveName, $directiveArgs, $field, $variables)
                ) {
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

    public function fillResultItems(DataloaderInterface $dataloader, array $ids_data_fields, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        // Obtain the data for the required object IDs
        $resultIDItems = $dataloader->getData(array_keys($ids_data_fields));

        // Enqueue the items
        $this->enqueueFillingResultItemsFromIDs($ids_data_fields);

        // Process them
        $this->processFillingResultItemsFromIDs($dataloader, $resultIDItems, $dbItems, $previousDBItems, $variables, $messages, $dbErrors, $dbWarnings, $schemaErrors, $schemaWarnings, $schemaDeprecations);
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
                    if (isset($this->fieldDirectiveCounter[$field][(string)$id][$fieldDirective])) {
                        // Increase counter and add to $fieldDirective
                        $fieldDirective .= self::REPEATED_DIRECTIVE_COUNTER_SEPARATOR.(++$this->fieldDirectiveCounter[$field][(string)$id][$fieldDirective]);
                    } else {
                        $this->fieldDirectiveCounter[$field][(string)$id][$fieldDirective] = 0;
                    }
                    // Store which ID/field this directive must process
                    if (in_array($field, $data_fields['direct'])) {
                        $this->fieldDirectiveIDFields[$fieldDirective][(string)$id]['direct'][] = $field;
                    }
                    if ($conditionalFields = $data_fields['conditional'][$field]) {
                        $this->fieldDirectiveIDFields[$fieldDirective][(string)$id]['conditional'][$field] = $conditionalFields;
                    }
                }
            }
        }
    }

    protected function processFillingResultItemsFromIDs(DataloaderInterface $dataloader, array &$resultIDItems, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        // Iterate while there are directives with data to be processed
        while (!empty($this->fieldDirectiveIDFields)) {
            $fieldDirectiveIDFields = $this->fieldDirectiveIDFields;
            // Now that we have all data, remove all entries from the inner stack.
            // It may be filled again with nested directives, when resolving the pipeline
            $this->fieldDirectiveIDFields = [];
            $this->fieldDirectiveCounter = [];

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
            $directivePipelineData = $this->resolveDirectivesIntoPipelineData($fieldDirectives, $fieldDirectiveFields, $variables, $schemaErrors, $schemaWarnings, $schemaDeprecations);

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
                $dataloader,
                $this,
                $pipelineIDsDataFields,
                $resultIDItems,
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
        // Get the value from a fieldValueResolver, from the first one that resolves it
        if ($fieldValueResolvers = $this->getFieldValueResolversForField($field)) {
            list(
                $field,
                $fieldName,
                $fieldArgs,
                $schemaErrors,
            ) = $this->dissectFieldForSchema($field);
            if ($maybeError = $fieldValueResolvers[0]->resolveSchemaValidationErrorDescription($this, $fieldName, $fieldArgs)) {
                $schemaErrors[] = $maybeError;
            }
            return $schemaErrors;
        }

        // If we reach here, no fieldValueResolver processes this field, which is an error
        $translationAPI = TranslationAPIFacade::getInstance();
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $fieldName = $fieldQueryInterpreter->getFieldName($field);
        return [
            sprintf(
                $translationAPI->__('No FieldValueResolver resolves field \'%s\'', 'pop-component-model'),
                $fieldName
            ),
        ];
    }

    public function resolveSchemaValidationWarningDescriptions(string $field, array &$variables = null): ?array
    {
        // Get the value from a fieldValueResolver, from the first one that resolves it
        if ($fieldValueResolvers = $this->getFieldValueResolversForField($field)) {
            list(
                $field,
                $fieldName,
                $fieldArgs,
                $schemaErrors,
                $schemaWarnings,
            ) = $this->dissectFieldForSchema($field);
            if ($maybeWarning = $fieldValueResolvers[0]->resolveSchemaValidationWarningDescription($this, $fieldName, $fieldArgs)) {
                $schemaWarnings[] = $maybeWarning;
            }
            return $schemaWarnings;
        }

        return null;
    }

    public function getSchemaDeprecationDescriptions(string $field, array &$variables = null): ?array
    {
        // Get the value from a fieldValueResolver, from the first one that resolves it
        if ($fieldValueResolvers = $this->getFieldValueResolversForField($field)) {
            list(
                $field,
                $fieldName,
                $fieldArgs,
                $schemaErrors,
                $schemaWarnings,
                $schemaDeprecations,
            ) = $this->dissectFieldForSchema($field);
            if ($maybeDeprecation = $fieldValueResolvers[0]->getSchemaFieldDeprecationDescription($this, $fieldName, $fieldArgs)) {
                $schemaDeprecations[] = $maybeDeprecation;
            }
            return $schemaDeprecations;
        }

        return null;
    }

    public function getSchemaFieldArgs(string $field): array
    {
        // Get the value from a fieldValueResolver, from the first one that resolves it
        if ($fieldValueResolvers = $this->getFieldValueResolversForField($field)) {
            $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
            $fieldName = $fieldQueryInterpreter->getFieldName($field);
            return $fieldValueResolvers[0]->getSchemaFieldArgs($this, $fieldName);
        }

        return [];
    }

    public function enableOrderedSchemaFieldArgs(string $field): bool
    {
        // Get the value from a fieldValueResolver, from the first one that resolves it
        if ($fieldValueResolvers = $this->getFieldValueResolversForField($field)) {
            $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
            $fieldName = $fieldQueryInterpreter->getFieldName($field);
            return $fieldValueResolvers[0]->enableOrderedSchemaFieldArgs($this, $fieldName);
        }

        return false;
    }

    public function resolveFieldDefaultDataloaderClass(string $field): ?string
    {
        // Get the value from a fieldValueResolver, from the first one that resolves it
        if ($fieldValueResolvers = $this->getFieldValueResolversForField($field)) {
            list(
                $field,
                $fieldName,
                $fieldArgs,
            ) = $this->dissectFieldForSchema($field);
            return $fieldValueResolvers[0]->resolveFieldDefaultDataloaderClass($this, $fieldName, $fieldArgs);
        }

        return null;
    }

    public function resolveValue($resultItem, string $field, ?array $variables = null, ?array $expressions = null, array $options = [])
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        // Get the value from a fieldValueResolver, from the first one who can deliver the value
        // (The fact that they resolve the fieldName doesn't mean that they will always resolve it for that specific $resultItem)
        if ($fieldValueResolvers = $this->getFieldValueResolversForField($field)) {
            $feedbackMessageStore = FeedbackMessageStoreFacade::getInstance();
            // Important: $validField becomes $field: remove all invalid fieldArgs before executing `resolveValue` on the fieldValueResolver
            list(
                $field,
                $fieldName,
                $fieldArgs,
                $schemaErrors,
                $schemaWarnings,
            ) = $this->dissectFieldForSchema($field);

            // Store the warnings to be read if needed
            if ($schemaWarnings) {
                $feedbackMessageStore->addSchemaWarnings($schemaWarnings);
            }
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

            foreach ($fieldValueResolvers as $fieldValueResolver) {
                // Also send the fieldResolver along, as to get the id of the $resultItem being passed
                if ($fieldValueResolver->resolveCanProcessResultItem($this, $resultItem, $fieldName, $fieldArgs)) {
                    if ($validateSchemaOnResultItem) {
                        if ($maybeError = $fieldValueResolver->resolveSchemaValidationErrorDescription($this, $fieldName, $fieldArgs)) {
                            return ErrorUtils::getValidationFailedError($fieldName, $fieldArgs, $maybeError);
                        }
                    }
                    if ($validationErrorDescription = $fieldValueResolver->getValidationErrorDescription($this, $resultItem, $fieldName, $fieldArgs)) {
                        return ErrorUtils::getValidationFailedError($fieldName, $fieldArgs, $validationErrorDescription);
                    }
                    return $fieldValueResolver->resolveValue($this, $resultItem, $fieldName, $fieldArgs);
                }
            }
            return ErrorUtils::getNoFieldValueResolverProcessesFieldError($this->getId($resultItem), $fieldName, $fieldArgs);
        }

        // Return an error to indicate that no fieldValueResolver processes this field, which is different than returning a null value.
        // Needed for compatibility with Dataloader_ConvertiblePostList (so that data-fields aimed for another post_type are not retrieved)
        $fieldName = $fieldQueryInterpreter->getFieldName($field);
        return ErrorUtils::getNoFieldError($fieldName);
    }

    protected function getFieldResolverSchemaId(string $class): string
    {
        if (!isset($this->fieldResolverSchemaIdsCache[$class])) {
            $this->fieldResolverSchemaIdsCache[$class] = $this->doGetFieldResolverSchemaId($class);

            // Log how the hash and the class are related
            $feedbackMessageStore = FeedbackMessageStoreFacade::getInstance();
            $translationAPI = TranslationAPIFacade::getInstance();
            $feedbackMessageStore->maybeAddLogEntry(
                sprintf(
                    $translationAPI->__('Field resolver with ID \'%s\' corresponds to class \'%s\'', 'pop-component-model'),
                    $this->fieldResolverSchemaIdsCache[$class],
                    $class
                )
            );
        }
        return $this->fieldResolverSchemaIdsCache[$class];
    }

    protected function doGetFieldResolverSchemaId(string $class): string
    {
        return hash('md5', $class);
    }

    public function getSchemaDefinition(array $fieldArgs = [], array $options = []): array
    {
        // Stop recursion
        $class = get_called_class();
        if (in_array($class, $options['processed'])) {
            return [
                SchemaDefinition::ARGNAME_RESOLVERID => $this->getFieldResolverSchemaId($class),
                SchemaDefinition::ARGNAME_RECURSION => true,
            ];
        }

        $options['processed'][] = $class;
        if (is_null($this->schemaDefinition)) {
            $this->schemaDefinition = [
                SchemaDefinition::ARGNAME_RESOLVERID => $this->getFieldResolverSchemaId($class),
            ];
            $this->addSchemaDefinition($fieldArgs, $options);
        }

        return $this->schemaDefinition;
    }

    protected function addSchemaDefinition(array $schemaFieldArgs = [], array $options = [])
    {
        $instanceManager = InstanceManagerFacade::getInstance();

        // Only in the root we output the operators and helpers
        $isRoot = $options['is-root'];
        unset($options['is-root']);

        // Add the directives
        $this->schemaDefinition[SchemaDefinition::ARGNAME_DIRECTIVES] = [];
        $directiveNameClasses = $this->getDirectiveNameClasses();
        foreach ($directiveNameClasses as $directiveName => $directiveClasses) {
            foreach ($directiveClasses as $directiveClass) {
                $directiveResolverInstance = $instanceManager->getInstance($directiveClass);
                // $directiveResolverInstance = new $directiveClass($directiveName);
                $isGlobal = $directiveResolverInstance->isGlobal($this);
                if (!$isGlobal || ($isGlobal && $isRoot)) {
                    $directiveSchemaDefinition = $directiveResolverInstance->getSchemaDefinitionForDirective($this);
                    if ($isGlobal) {
                        $this->schemaDefinition[SchemaDefinition::ARGNAME_GLOBAL_DIRECTIVES][] = $directiveSchemaDefinition;
                    } else {
                        $this->schemaDefinition[SchemaDefinition::ARGNAME_DIRECTIVES][] = $directiveSchemaDefinition;
                    }
                }
            }
        }

        // Remove all fields which are not resolved by any unit
        $this->schemaDefinition[SchemaDefinition::ARGNAME_FIELDS] = [];
        $this->calculateAllFieldValueResolvers();
        foreach (array_filter($this->fieldValueResolvers) as $field => $fieldValueResolvers) {
            // Copy the properties from the schemaFieldArgs to the fieldArgs, in particular "deep"
            list(
                $field,
                $fieldName,
                $fieldArgs,
            ) = $this->dissectFieldForSchema($field);
            if (!is_null($field)) {
                // Get the documentation from the first element
                $fieldValueResolver = $fieldValueResolvers[0];
                $isOperatorOrHelper = $fieldValueResolver->isOperatorOrHelper($this, $fieldName);
                if (!$isOperatorOrHelper || ($isOperatorOrHelper && $isRoot)) {
                    $fieldArgs = array_merge(
                        $schemaFieldArgs,
                        $fieldArgs
                    );
                    $fieldSchemaDefinition = $fieldValueResolver->getSchemaDefinitionForField($this, $fieldName, $fieldArgs);
                    // Add subfield schema if it is deep, and this fieldResolver has not been processed yet
                    if ($fieldArgs['deep']) {
                        // If this field is relational, then add its own schema
                        if ($fieldDataloaderClass = $this->resolveFieldDefaultDataloaderClass($field)) {
                            // Append subfields' schema
                            $fieldDataloader = $instanceManager->getInstance($fieldDataloaderClass);
                            if ($fieldResolverClass = $fieldDataloader->getFieldResolverClass()) {
                                $fieldResolver = $instanceManager->getInstance($fieldResolverClass);
                                $fieldSchemaDefinition[SchemaDefinition::ARGNAME_RESOLVER] = $fieldResolver->getSchemaDefinition($fieldArgs, $options);
                            }
                        }
                    }

                    if ($isOperatorOrHelper) {
                        $this->schemaDefinition[SchemaDefinition::ARGNAME_OPERATORS_AND_HELPERS][] = $fieldSchemaDefinition;
                    } else {
                        $this->schemaDefinition[SchemaDefinition::ARGNAME_FIELDS][] = $fieldSchemaDefinition;
                    }
                }
            }
        }
    }

    protected function calculateAllFieldValueResolvers()
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();

        // Iterate classes from the current class towards the parent classes until finding fieldResolver that satisfies processing this field
        $class = get_called_class();
        do {
            foreach ($attachableExtensionManager->getExtensionClasses($class, AttachableExtensionGroups::FIELDVALUERESOLVERS) as $extensionClass => $extensionPriority) {
                // Process the fields which have not been processed yet
                foreach (array_diff($extensionClass::getFieldNamesToResolve(), array_unique(array_map([$fieldQueryInterpreter, 'getFieldName'], array_keys($this->fieldValueResolvers)))) as $fieldName) {
                    // Watch out here: no fieldArgs!!!! So this deals with the base case (static), not with all cases (runtime)
                    $this->getFieldValueResolversForField($fieldName);
                }
            }
            // Otherwise, continue iterating for the class parents
        } while ($class = get_parent_class($class));
    }

    protected function getFieldValueResolversForField(string $field): array
    {
        // Calculate the fieldValueResolver to process this field if not already in the cache
        // If none is found, this value will be set to NULL. This is needed to stop attempting to find the fieldValueResolver
        if (!isset($this->fieldValueResolvers[$field])) {
            $this->fieldValueResolvers[$field] = $this->calculateFieldValueResolversForField($field);
        }

        return $this->fieldValueResolvers[$field];
    }

    public function hasFieldValueResolversForField(string $field): bool
    {
        return !empty($this->getFieldValueResolversForField($field));
    }

    protected function calculateFieldValueResolversForField(string $field): array
    {
        // Important: here we CAN'T use `dissectFieldForSchema` to get the fieldArgs, because it will attempt to validate them
        // To validate them, the fieldQueryInterpreter needs to know the schema, so it once again calls functions from this fieldResolver
        // Generating an infinite loop
        // Then, just to find out which fieldValueResolvers will process this field, crudely obtain the fieldArgs, with NO schema-based validation!
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

        // Iterate classes from the current class towards the parent classes until finding fieldResolver that satisfies processing this field
        $class = get_called_class();
        $fieldValueResolvers = [];
        do {
            // All the Units and their priorities for this class level
            $classFieldResolverPriorities = [];
            $classFieldValueResolvers = [];

            // Important: do array_reverse to enable more specific hooks, which are initialized later on in the project, to be the chosen ones (if their priority is the same)
            foreach (array_reverse($attachableExtensionManager->getExtensionClasses($class, AttachableExtensionGroups::FIELDVALUERESOLVERS)) as $extensionClass => $extensionPriority) {
                // Check if this fieldValueResolver can process this field, and if its priority is bigger than the previous found instance attached to the same class
                if (in_array($fieldName, $extensionClass::getFieldNamesToResolve())) {
                    // Check that the fieldValueResolver can handle the field based on other parameters (eg: "version" in the fieldArgs)
                    $fieldValueResolver = $instanceManager->getInstance($extensionClass);
                    if ($fieldValueResolver->resolveCanProcess($this, $fieldName, $fieldArgs)) {
                        $classFieldResolverPriorities[] = $extensionPriority;
                        $classFieldValueResolvers[] = $fieldValueResolver;
                    }
                }
            }
            // Sort the found units by their priority, and then add to the stack of all units, for all classes
            // Higher priority means they execute first!
            array_multisort($classFieldResolverPriorities, SORT_DESC, SORT_NUMERIC, $classFieldValueResolvers);
            $fieldValueResolvers = array_merge(
                $fieldValueResolvers,
                $classFieldValueResolvers
            );
            // Continue iterating for the class parents
        } while ($class = get_parent_class($class));

        // Return all the units that resolve the fieldName
        return $fieldValueResolvers;
    }

    protected function calculateFieldDirectiveNameClasses(): array
    {
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();

        $directiveNameClasses = [];

        // Iterate classes from the current class towards the parent classes until finding fieldResolver that satisfies processing this field
        $class = get_called_class();
        do {
            // Important: do array_reverse to enable more specific hooks, which are initialized later on in the project, to be the chosen ones (if their priority is the same)
            $extensionClassPriorities = array_reverse($attachableExtensionManager->getExtensionClasses($class, AttachableExtensionGroups::FIELDDIRECTIVERESOLVERS));
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

        // Iterate classes from the current class towards the parent classes until finding fieldResolver that satisfies processing this field
        $class = get_called_class();
        do {
            foreach ($attachableExtensionManager->getExtensionClasses($class, AttachableExtensionGroups::FIELDVALUERESOLVERS) as $extensionClass => $extensionPriority) {
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
