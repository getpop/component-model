<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionTrait;
use PoP\ComponentModel\Facades\ModuleProcessors\ModuleProcessorManagerFacade;
use PoP\ComponentModel\GeneralUtils;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Engine\EngineFacade;

abstract class AbstractFieldValueResolver implements FieldValueResolverInterface
{
    /**
     * This class is attached to a FieldResolver
     */
    use AttachableExtensionTrait;

    /**
     * Indicates if the fieldValueResolver can process this combination of fieldName and fieldArgs
     * It is required to support a multiverse of fields: different fieldValueResolvers can resolve the field, based on the required version (passed through $fieldArgs['branch'])
     *
     * @param string $fieldName
     * @param array $fieldArgs
     * @return boolean
     */
    public function resolveCanProcess(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): bool
    {
        return true;
    }
    public function resolveSchemaValidationErrorDescription(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        // Iterate all the mandatory fieldArgs and, if they are not present, throw an error
        if ($schemaDefinitionResolver = $this->getSchemaDefinitionResolver($fieldResolver, $fieldName, $fieldArgs)) {
            if ($args = $schemaDefinitionResolver->getSchemaFieldArgs($fieldResolver, $fieldName)) {
                if ($mandatoryArgs = array_filter(
                    $args,
                    function($arg) {
                        return isset($arg[SchemaDefinition::ARGNAME_MANDATORY]) && $arg[SchemaDefinition::ARGNAME_MANDATORY];
                    }
                )) {
                    if ($maybeError = $this->validateNotMissingFieldArguments(
                        $fieldResolver,
                        array_map(function($arg) {
                            return $arg[SchemaDefinition::ARGNAME_NAME];
                        }, $mandatoryArgs),
                        $fieldName,
                        $fieldArgs
                    )) {
                        return $maybeError;
                    }
                }
            }
        }
        return null;
    }

    protected function validateNotMissingFieldArguments(FieldResolverInterface $fieldResolver, $fieldArgumentProperties, string $fieldName, array $fieldArgs = []): ?string
    {
        $missing = [];
        foreach ($fieldArgumentProperties as $fieldArgumentProperty) {
            if (!array_key_exists($fieldArgumentProperty, $fieldArgs)) {
                $missing[] = $fieldArgumentProperty;
            }
        }
        if ($missing) {
            $translationAPI = TranslationAPIFacade::getInstance();
            return count($missing) == 1 ?
                sprintf(
                    $translationAPI->__('Argument \'%s\' cannot be empty, so field \'%s\' has been ignored', 'pop-component-model'),
                    $missing[0],
                    $fieldName
                ) :
                sprintf(
                    $translationAPI->__('Arguments \'%s\' cannot be empty, so field \'%s\' has been ignored', 'pop-component-model'),
                    implode($translationAPI->__('\', \''), $missing),
                    $fieldName
                );
        }
        return null;
    }

    /**
     * Return the object implementing the schema definition for this fieldValueResolver
     *
     * @return void
     */
    public function getSchemaDefinitionResolver(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = [])
    {
        return null;
    }

    /**
     * Get the "schema" properties as for the fieldName
     *
     * @return array
     */
    public function getSchemaDefinitionForField(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): array
    {
        $documentation = [
            SchemaDefinition::ARGNAME_NAME => $fieldName,
        ];
        if ($schemaDefinitionResolver = $this->getSchemaDefinitionResolver($fieldResolver, $fieldName, $fieldArgs)) {
            if ($type = $schemaDefinitionResolver->getSchemaFieldType($fieldResolver, $fieldName)) {
                $documentation[SchemaDefinition::ARGNAME_TYPE] = $type;
            }
            if ($description = $schemaDefinitionResolver->getSchemaFieldDescription($fieldResolver, $fieldName)) {
                $documentation[SchemaDefinition::ARGNAME_DESCRIPTION] = $description;
            }
            if ($deprecationDescription = $schemaDefinitionResolver->getSchemaFieldDeprecationDescription($fieldResolver, $fieldName, $fieldArgs)) {
                $documentation[SchemaDefinition::ARGNAME_DEPRECATED] = true;
                $documentation[SchemaDefinition::ARGNAME_DEPRECATEDDESCRIPTION] = $deprecationDescription;
            }
            if ($args = $schemaDefinitionResolver->getSchemaFieldArgs($fieldResolver, $fieldName)) {
                $documentation[SchemaDefinition::ARGNAME_ARGS] = $args;
            }
        }
        if (!is_null($this->resolveFieldDefaultDataloaderClass($fieldResolver, $fieldName, $fieldArgs))) {
            $documentation[SchemaDefinition::ARGNAME_RELATIONAL] = true;
        }
        $this->addFieldDocumentation($documentation, $fieldName);
        return $documentation;
    }

    public function enableOrderedFieldDocumentationArgs(FieldResolverInterface $fieldResolver, string $fieldName): bool
    {
        return true;
    }

    public function resolveSchemaValidationWarningDescription(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        return null;
    }

    /**
     * Function to override
     */
    protected function addFieldDocumentation(array &$documentation, string $fieldName)
    {
    }

    protected function getFieldArgumentsDocumentation(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): array
    {
        if ($filterDataloadingModule = $this->getFieldDefaultFilterDataloadingModule($fieldResolver, $fieldName, $fieldArgs)) {
            $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
            $filterqueryargs_modules = $moduleprocessor_manager->getProcessor($filterDataloadingModule)->getDataloadQueryArgsFilteringModules($filterDataloadingModule);
            return GeneralUtils::arrayFlatten(array_map(function($module) use($moduleprocessor_manager) {
                return $moduleprocessor_manager->getProcessor($module)->getFilterDocumentationItems($module);
            }, $filterqueryargs_modules));
        }

        return [];
    }

    public function resolveCanProcessResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []): bool
    {
        return true;
    }

    protected function getValidationCheckpoints(FieldResolverInterface $fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []): ?array
    {
        return null;
    }

    protected function getValidationCheckpointsErrorMessage(FieldResolverInterface $fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []): ?string
    {
        return null;
    }

    public function getValidationErrorDescription(FieldResolverInterface $fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []): ?string
    {
        // Can perform validation through checkpoints
        if ($checkpoints = $this->getValidationCheckpoints($fieldResolver, $resultItem, $fieldName, $fieldArgs)) {
            $engine = EngineFacade::getInstance();
            $validation = $engine->validateCheckpoints($checkpoints);
            if (\PoP\ComponentModel\GeneralUtils::isError($validation)) {
                // Check if there is a custom error message
                $message = $this->getValidationCheckpointsErrorMessage($fieldResolver, $resultItem, $fieldName, $fieldArgs);
                if (is_null($message)) {
                    // Return a generic message
                    $error = $validation;
                    $translationAPI = TranslationAPIFacade::getInstance();
                    return $error->getErrorMessage() ?
                        $error->getErrorMessage() :
                        sprintf(
                            $translationAPI->__('Validation with code \'%s\' failed', ''),
                            $error->getErrorCode()
                        );
                }
                return $message;
            }
        }

        return null;
    }

    public function resolveValue(FieldResolverInterface $fieldResolver, $resultItem, string $fieldName, array $fieldArgs = [])
    {
        return null;
    }

    public function resolveFieldDefaultDataloaderClass(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        return null;
    }

    protected function getFieldDefaultFilterDataloadingModule(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): ?array
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $dataloaderClass = $this->resolveFieldDefaultDataloaderClass($fieldResolver, $fieldName, $fieldArgs);
        $dataloader = $instanceManager->getInstance($dataloaderClass);
        return $dataloader->getFilterDataloadingModule();
    }

    protected function addFilterDataloadQueryArgs(array &$options, FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = [])
    {
        $options['filter-dataload-query-args'] = [
            'source' => $fieldArgs,
            'module' => $this->getFieldDefaultFilterDataloadingModule($fieldResolver, $fieldName, $fieldArgs),
        ];
    }
}
