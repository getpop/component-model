<?php
namespace PoP\ComponentModel;
use League\Pipeline\PipelineBuilder;
use PoP\ComponentModel\Schema\FieldQueryUtils;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\DirectiveResolvers\ValidateDirectiveResolver;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\DirectiveResolvers\ResolveValueAndMergeDirectiveResolver;
use PoP\ComponentModel\Facades\Managers\InstanceManagerFacade;
use PoP\ComponentModel\Facades\Schema\ErrorMessageStoreFacade;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;
use PoP\ComponentModel\DirectivePipeline\DirectivePipelineDecorator;
use PoP\ComponentModel\Facades\AttachableExtensions\AttachableExtensionManagerFacade;

abstract class AbstractFieldResolver
{
    /**
     * Cache of which fieldValueResolvers will process the given field
     *
     * @var array
     */
    protected $fieldValueResolvers = [];
    // protected $fieldDirectiveResolvers = [];
    protected $schemaDocumentation;
    protected $fieldNamesToResolve;
    protected $directiveNameClasses;
    protected $safeVars;

    private $fieldDirectiveIDsFields = [];
    private $directiveResultSet = [];
    private $fieldDirectivePipelineInstanceCache = [];
    private $fieldDirectiveInstanceCache = [];
    private $fieldDirectivesFromFieldCache = [];
    private $dissectedFieldForSchemaCache = [];

    abstract public function getId($resultItem);
    abstract public function getIdFieldDataloaderClass();

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

    protected function getFieldDirectivePipeline(string $fieldDirectives, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): DirectivePipelineDecorator
    {
        // Pipeline cache
        if (is_null($this->fieldDirectivePipelineInstanceCache[$fieldDirectives])) {
            $translationAPI = TranslationAPIFacade::getInstance();
            $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
            $pipelineBuilder = new PipelineBuilder();
            $directiveNameClasses = $this->getDirectiveNameClasses();
            // Initialize with the default values, adding "validate" and "merge" if not there yet
            $directiveSet = $this->extractAndNormalizeFieldDirectives($fieldDirectives);
            foreach ($directiveSet as $directive) {
                $fieldDirective = $fieldQueryInterpreter->convertDirectiveToFieldDirective($directive);
                if (is_null($this->fieldDirectiveInstanceCache[$fieldDirective])) {
                    // Validate schema (eg of error in schema: ?fields=posts<include(if:this-field-doesnt-exist())>)
                    list(
                        $validFieldDirective,
                        $directiveName,
                    ) = $this->dissectAndValidateDirectiveForSchema($fieldDirective, $schemaErrors, $schemaWarnings, $schemaDeprecations);
                    // Check that the directive is a valid one (eg: no schema errors)
                    if (is_null($validFieldDirective)) {
                        $schemaErrors[$directiveName] = $translationAPI->__('This directive can\'t be processed due to previous errors', 'pop-component-model');
                        continue;
                    }
                    $directiveName = $fieldQueryInterpreter->getDirectiveName($directive);
                    $directiveClass = $directiveNameClasses[$directiveName];
                    // If there is no directive with this name, show an error and skip it
                    if (is_null($directiveClass)) {
                        $schemaErrors[$directiveName][] = sprintf(
                            $translationAPI->__('No DirectiveResolver resolves directive with name \'%s\'', 'pop-component-model'),
                            $directiveName
                        );
                        continue;
                    }
                    // Add the directive as a pipeline stage
                    $this->fieldDirectiveInstanceCache[$fieldDirective] = new $directiveClass($validFieldDirective);
                }
                $directiveResolverInstance = $this->fieldDirectiveInstanceCache[$fieldDirective];
                $pipelineBuilder->add($directiveResolverInstance);
            }
            // Build the pipeline
            $this->fieldDirectivePipelineInstanceCache[$fieldDirectives] = new DirectivePipelineDecorator($pipelineBuilder->build());
        }
        return $this->fieldDirectivePipelineInstanceCache[$fieldDirectives];
    }
    protected function dissectAndValidateDirectiveForSchema(string $directive, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        // First validate schema (eg of error in schema: ?fields=posts<include(if:this-field-doesnt-exist())>)
        list(
            $directive,
            $directiveName,
            $directiveArgs,
            $directiveSchemaErrors,
            $directiveSchemaWarnings,
            $directiveSchemaDeprecations
        ) = $fieldQueryInterpreter->extractFieldArgumentsForSchema($this, $directive);

        // If there were errors, save them and remove the corresponding args from the directive
        if ($directiveSchemaErrors || $directiveSchemaWarnings || $directiveSchemaDeprecations) {
            $directiveOutputKey = $fieldQueryInterpreter->getFieldOutputKey($directive);
            foreach ($directiveSchemaErrors as $error) {
                $schemaErrors[$directiveOutputKey][] = $error;
            }
            foreach ($directiveSchemaWarnings as $warning) {
                $schemaWarnings[$directiveOutputKey][] = $warning;
            }
            foreach ($directiveSchemaDeprecations as $deprecation) {
                $schemaDeprecations[$directiveOutputKey][] = $deprecation;
            }
        }
        return [
            $directive,
            $directiveName,
            $directiveArgs,
        ];
    }

    protected function extractAndNormalizeFieldDirectives(string $fieldDirectives): array
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $fieldDirectiveSet = $fieldQueryInterpreter->extractFieldDirectives($fieldDirectives);

        // Stages "validate" and "resolve value and merge" are mandatory. Check if they were provided, otherwise add them:
        // 1. Start with the "validate" stage
        $hasValidate = array_reduce($fieldDirectiveSet, function($hasItem, $directive) use ($fieldQueryInterpreter) {
            $hasItem = $hasItem || $fieldQueryInterpreter->getDirectiveName($directive) == ValidateDirectiveResolver::DIRECTIVE_NAME;
            return $hasItem;
        }, false);
        if (!$hasValidate) {
            // Add it at the beginning
            array_unshift($fieldDirectiveSet, $fieldQueryInterpreter->listFieldDirective(ValidateDirectiveResolver::DIRECTIVE_NAME));
        }

        // 2. End with the "resolve value and merge" stage
        $hasMerge = array_reduce($fieldDirectiveSet, function($hasItem, $directive) use ($fieldQueryInterpreter) {
            $hasItem = $hasItem || $fieldQueryInterpreter->getDirectiveName($directive) == ResolveValueAndMergeDirectiveResolver::DIRECTIVE_NAME;
            return $hasItem;
        }, false);
        if (!$hasMerge) {
            // Add it at the end
            $fieldDirectiveSet[] = $fieldQueryInterpreter->listFieldDirective(ResolveValueAndMergeDirectiveResolver::DIRECTIVE_NAME);
        }

        return $fieldDirectiveSet;
    }

    final public function addDataitemsToHeap(array $ids_data_fields, array &$resultIDItems)
    {
        // Collect all combinations of ID/data-fields for each directive
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        foreach ($ids_data_fields as $id => $data_fields) {
            foreach ($data_fields['direct'] as $field) {
                if (is_null($this->fieldDirectivesFromFieldCache[$field])) {
                    $this->fieldDirectivesFromFieldCache[$field] = $fieldQueryInterpreter->getFieldDirectives($field) ?? '';
                }
                $fieldDirectives = $this->fieldDirectivesFromFieldCache[$field];
                $this->directiveResultSet[$fieldDirectives][$id] = $resultIDItems[$id];
                $this->fieldDirectiveIDsFields[$fieldDirectives][$id]['direct'][] = $field;
                $this->fieldDirectiveIDsFields[$fieldDirectives][$id]['conditional'] = $this->fieldDirectiveIDsFields[$fieldDirectives][$id]['conditional'] ?? [];
                if ($conditionalFields = $ids_data_fields[$id]['conditional'][$field]) {
                    $this->fieldDirectiveIDsFields[$fieldDirectives][$id]['conditional'][$field] = array_merge_recursive(
                        $this->fieldDirectiveIDsFields[$fieldDirectives][$id]['conditional'][$field] ?? [],
                        $conditionalFields
                    );
                }
            }
        }
    }

    final public function addDataitems(array $ids_data_fields, array &$resultIDItems, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $this->addDataitemsToHeap($ids_data_fields, $resultIDItems);

        // Iterate while there are directives with data to be processed
        while (!empty($this->fieldDirectiveIDsFields)) {
            // Move the pointer to the first element, and get it
            reset($this->fieldDirectiveIDsFields);
            $fieldDirectives = key($this->fieldDirectiveIDsFields);
            $idsDataFields = $this->fieldDirectiveIDsFields[$fieldDirectives];
            $directiveResultSet = $this->directiveResultSet[$fieldDirectives];

            // Remove the directive element from the array, so it doesn't process it anymore
            unset($this->fieldDirectiveIDsFields[$fieldDirectives]);
            unset($this->directiveResultSet[$fieldDirectives]);

            // If no ids to execute, then skip
            if (empty($idsDataFields)) {
                continue;
            }

            // From the fieldDirectiveName get the class that processes it. If null, the users passed a wrong name through the API, so show an error
            $directivePipeline = $this->getFieldDirectivePipeline($fieldDirectives, $schemaErrors, $schemaWarnings, $schemaDeprecations);
            $directivePipeline->resolvePipeline(
                $this,
                $directiveResultSet,
                $idsDataFields,
                $dbItems,
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








    public function resolveSchemaValidationErrorDescriptions(string $field): ?array
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
        return [
            sprintf(
                $translationAPI->__('No FieldValueResolver resolves field \'%s\'', 'pop-component-model'),
                $fieldName
            ),
        ];
    }

    public function getFieldDocumentationWarningDescriptions(string $field): ?array
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

    public function getFieldDocumentationDeprecationDescriptions(string $field): ?array
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
            if ($maybeDeprecation = $fieldValueResolvers[0]->getFieldDocumentationDeprecationDescription($this, $fieldName, $fieldArgs)) {
                $schemaDeprecations[] = $maybeDeprecation;
            }
            return $schemaDeprecations;
        }

        return null;
    }

    public function getFieldDocumentationArgs(string $field): array
    {
        // Get the value from a fieldValueResolver, from the first one that resolves it
        if ($fieldValueResolvers = $this->getFieldValueResolversForField($field)) {
            $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
            $fieldName = $fieldQueryInterpreter->getFieldName($field);
            return $fieldValueResolvers[0]->getFieldDocumentationArgs($this, $fieldName);
        }

        return [];
    }

    public function enableOrderedFieldDocumentationArgs(string $field): bool
    {
        // Get the value from a fieldValueResolver, from the first one that resolves it
        if ($fieldValueResolvers = $this->getFieldValueResolversForField($field)) {
            $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
            $fieldName = $fieldQueryInterpreter->getFieldName($field);
            return $fieldValueResolvers[0]->enableOrderedFieldDocumentationArgs($this, $fieldName);
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

    public function resolveValue($resultItem, string $field)
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        // Get the value from a fieldValueResolver, from the first one who can deliver the value
        // (The fact that they resolve the fieldName doesn't mean that they will always resolve it for that specific $resultItem)
        if ($fieldValueResolvers = $this->getFieldValueResolversForField($field)) {
            // Important: $validField becomes $field: remove all invalid fieldArgs before executing `resolveValue` on the fieldValueResolver
            list(
                $field,
                $fieldName,
                $fieldArgs,
            ) = $this->dissectFieldForSchema($field);

            // Once again, the $validField becomes the $field
            list(
                $field,
                $fieldName,
                $fieldArgs,
                $dbErrors,
                $dbWarnings
            ) = $fieldQueryInterpreter->extractFieldArgumentsForResultItem($this, $resultItem, $field);
            // Store the warnings to be read if needed
            if ($dbWarnings) {
                $errorMessageStore = ErrorMessageStoreFacade::getInstance();
                $errorMessageStore->addDBWarnings($dbWarnings);
            }
            if ($dbErrors) {
                return ErrorUtils::getNestedDBErrorsFieldError($dbErrors, $fieldName);
            }
            // Before resolving the fieldArgValues which are fields:
            // Calculate $validateSchemaOnResultItem: if any value containes a field, then we must perform the schemaValidation on the item, such as checking that all mandatory fields are there
            // For instance: After resolving a field and being casted it may be incorrect, so the value is invalidated, and after the schemaValidation the proper error is shown
            $validateSchemaOnResultItem = FieldQueryUtils::isAnyFieldArgumentValueAField(
                array_values(
                    $fieldQueryInterpreter->extractFieldArguments($this, $field)
                )
            );
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

    protected function getFieldResolverSchemaId(string $class): string {
        return hash('md5', $class);
    }

    public function getSchemaDocumentation(array $fieldArgs = [], array $options = []): array
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
        if (is_null($this->schemaDocumentation)) {
            $this->schemaDocumentation = [
                SchemaDefinition::ARGNAME_RESOLVERID => $this->getFieldResolverSchemaId($class),
            ];
            $this->addSchemaDocumentation($fieldArgs, $options);
        }

        return $this->schemaDocumentation;
    }

    protected function addSchemaDocumentation(array $schemaFieldArgs = [], array $options = [])
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $this->calculateAllFieldValueResolvers();
        // if ($options['is-root']) {
        //     $this->schemaDocumentation['global-fields'] = $this->getGlobalFieldSchemaDocumentation();
        //     unset($options['is-root']);
        // }
        $this->schemaDocumentation[SchemaDefinition::ARGNAME_FIELDS] = [];

        // Remove all fields which are not resolved by any unit
        foreach (array_filter($this->fieldValueResolvers) as $field => $fieldValueResolvers) {
            // Copy the properties from the schemaFieldArgs to the fieldArgs, in particular "deep"
            list(
                $field,
                $fieldName,
                $fieldArgs,
            ) = $this->dissectFieldForSchema($field);
            if (!is_null($field)) {
                $fieldArgs = array_merge(
                    $schemaFieldArgs,
                    $fieldArgs
                );

                // Get the documentation from the first element
                $fieldValueResolver = $fieldValueResolvers[0];
                $fieldDocumentation = $fieldValueResolver->getFieldDocumentation($this, $fieldName, $fieldArgs);

                // Add subfield schema if it is deep, and this fieldResolver has not been processed yet
                if ($fieldArgs['deep']) {
                    // If this field is relational, then add its own schema
                    if ($fieldDataloaderClass = $this->resolveFieldDefaultDataloaderClass($field)) {
                        // Append subfields' schema
                        $fieldDataloader = $instanceManager->getInstance($fieldDataloaderClass);
                        if ($fieldResolverClass = $fieldDataloader->getFieldResolverClass()) {
                            $fieldResolver = $instanceManager->getInstance($fieldResolverClass);
                            $fieldDocumentation[SchemaDefinition::ARGNAME_RESOLVER] = $fieldResolver->getSchemaDocumentation($fieldArgs, $options);
                        }
                    }
                }

                $this->schemaDocumentation[SchemaDefinition::ARGNAME_FIELDS][] = $fieldDocumentation;
            }
        }
    }

    protected function calculateAllFieldValueResolvers()
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();

        // Iterate classes from the current class towards the parent classes until finding fieldResolver that satisfies processing this field
        $class = get_called_class();
        do {
            foreach (array_reverse($attachableExtensionManager->getExtensionClasses($class, \PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups::FIELDVALUERESOLVERS)) as $extensionClass => $extensionPriority) {
                // Process the fields which have not been processed yet
                $instance = $instanceManager->getInstance($extensionClass);
                foreach (array_diff($instance->getFieldNamesToResolve(), array_unique(array_map([$fieldQueryInterpreter, 'getFieldName'], array_keys($this->fieldValueResolvers)))) as $fieldName) {
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
        list(
            $field,
            $fieldName,
            $fieldArgs,
        ) = $this->dissectFieldForSchema($field);

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
            foreach (array_reverse($attachableExtensionManager->getExtensionClasses($class, \PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups::FIELDVALUERESOLVERS)) as $extensionClass => $extensionPriority) {
                // Check if this fieldValueResolver can process this field, and if its priority is bigger than the previous found instance attached to the same class
                $fieldValueResolver = $instanceManager->getInstance($extensionClass);
                if (in_array($fieldName, $fieldValueResolver->getFieldNamesToResolve())) {
                    // Check that the fieldValueResolver can handle the field based on other parameters (eg: "version" in the fieldArgs)
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
        // $instanceManager = InstanceManagerFacade::getInstance();
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();

        $ret = [];

        // Iterate classes from the current class towards the parent classes until finding fieldResolver that satisfies processing this field
        $class = get_called_class();
        do {
            // Important: do array_reverse to enable more specific hooks, which are initialized later on in the project, to be the chosen ones (if their priority is the same)
            foreach ($attachableExtensionManager->getExtensionClasses($class, \PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups::FIELDDIRECTIVERESOLVERS) as $extensionClass => $extensionPriority) {
                // $directiveResolver = $instanceManager->getInstance($extensionClass);
                // // Don't override classes for those already-set directive names
                // $directiveName = $directiveResolver->getDirectiveName();
                $directiveName = $extensionClass::DIRECTIVE_NAME;
                if (!in_array($directiveName, array_keys($ret))) {
                    $ret[$directiveName] = $extensionClass;
                }
            }
            // Continue iterating for the class parents
        } while ($class = get_parent_class($class));

        return $ret;
    }

    protected function calculateFieldNamesToResolve(): array
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();

        // The ID is mandatory, since under this key is the data stored in the database object
        $ret = ['id'];

        // Iterate classes from the current class towards the parent classes until finding fieldResolver that satisfies processing this field
        $class = get_called_class();
        do {
            // Important: do array_reverse to enable more specific hooks, which are initialized later on in the project, to be the chosen ones (if their priority is the same)
            foreach ($attachableExtensionManager->getExtensionClasses($class, \PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups::FIELDVALUERESOLVERS) as $extensionClass => $extensionPriority) {
                $fieldValueResolver = $instanceManager->getInstance($extensionClass);
                $ret = array_merge(
                    $ret,
                    $fieldValueResolver->getFieldNamesToResolve()
                );
            }
            // Continue iterating for the class parents
        } while ($class = get_parent_class($class));

        return array_values(array_unique($ret));
    }
}
