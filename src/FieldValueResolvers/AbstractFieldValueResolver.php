<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use PoP\ComponentModel\GeneralUtils;
use PoP\ComponentModel\Schema\SchemaHelpers;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\Facades\Engine\EngineFacade;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionTrait;
use PoP\ComponentModel\Facades\ModuleProcessors\ModuleProcessorManagerFacade;

abstract class AbstractFieldValueResolver implements FieldValueResolverInterface, FieldValueResolverSchemaInterface
{
    /**
     * This class is attached to a TypeResolver
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
    public function resolveCanProcess(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): bool
    {
        return true;
    }
    public function resolveSchemaValidationErrorDescription(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        // Iterate all the mandatory fieldArgs and, if they are not present, throw an error
        if ($schemaFieldArgs = $this->getSchemaFieldArgs($typeResolver, $fieldName)) {
            if ($mandatoryArgs = SchemaHelpers::getSchemaMandatoryFieldArgs($schemaFieldArgs)) {
                if ($maybeError = $this->validateNotMissingFieldArguments(
                    SchemaHelpers::getSchemaFieldArgNames($mandatoryArgs),
                    $fieldName,
                    $fieldArgs
                )) {
                    return $maybeError;
                }
            }
        }
        return null;
    }

    protected function validateNotMissingFieldArguments(array $fieldArgumentProperties, string $fieldName, array $fieldArgs = []): ?string
    {
        if ($missing = SchemaHelpers::getMissingFieldArgs($fieldArgumentProperties, $fieldArgs)) {
            $translationAPI = TranslationAPIFacade::getInstance();
            return count($missing) == 1 ?
                sprintf(
                    $translationAPI->__('Field argument \'%s\' cannot be empty, so field \'%s\' has been ignored', 'pop-component-model'),
                    $missing[0],
                    $fieldName
                ) :
                sprintf(
                    $translationAPI->__('Field arguments \'%s\' cannot be empty, so field \'%s\' has been ignored', 'pop-component-model'),
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
    public function getSchemaDefinitionResolver(TypeResolverInterface $typeResolver)
    {
        return null;
    }

    public function getSchemaFieldType(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        if ($schemaDefinitionResolver = $this->getSchemaDefinitionResolver($typeResolver)) {
            return $schemaDefinitionResolver->getSchemaFieldType($typeResolver, $fieldName);
        }
        return null;
    }

    public function getSchemaFieldDescription(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        if ($schemaDefinitionResolver = $this->getSchemaDefinitionResolver($typeResolver)) {
            return $schemaDefinitionResolver->getSchemaFieldDescription($typeResolver, $fieldName);
        }
        return null;
    }

    public function getSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): array
    {
        if ($schemaDefinitionResolver = $this->getSchemaDefinitionResolver($typeResolver)) {
            return $schemaDefinitionResolver->getSchemaFieldArgs($typeResolver, $fieldName);
        }
        return [];
    }

    public function getSchemaFieldDeprecationDescription(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        if ($schemaDefinitionResolver = $this->getSchemaDefinitionResolver($typeResolver)) {
            return $schemaDefinitionResolver->getSchemaFieldDeprecationDescription($typeResolver, $fieldName, $fieldArgs);
        }
        return null;
    }

    public function isOperatorOrHelper(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        if ($schemaDefinitionResolver = $this->getSchemaDefinitionResolver($typeResolver)) {
            return $schemaDefinitionResolver->isOperatorOrHelper($typeResolver, $fieldName);
        }
        return false;
    }

    /**
     * Get the "schema" properties as for the fieldName
     *
     * @return array
     */
    public function getSchemaDefinitionForField(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): array
    {
        $schemaDefinition = [
            SchemaDefinition::ARGNAME_NAME => $fieldName,
        ];
        if ($schemaDefinitionResolver = $this->getSchemaDefinitionResolver($typeResolver, $fieldName, $fieldArgs)) {
            if ($type = $schemaDefinitionResolver->getSchemaFieldType($typeResolver, $fieldName)) {
                $schemaDefinition[SchemaDefinition::ARGNAME_TYPE] = $type;
            }
            if ($description = $schemaDefinitionResolver->getSchemaFieldDescription($typeResolver, $fieldName)) {
                $schemaDefinition[SchemaDefinition::ARGNAME_DESCRIPTION] = $description;
            }
            if ($deprecationDescription = $schemaDefinitionResolver->getSchemaFieldDeprecationDescription($typeResolver, $fieldName, $fieldArgs)) {
                $schemaDefinition[SchemaDefinition::ARGNAME_DEPRECATED] = true;
                $schemaDefinition[SchemaDefinition::ARGNAME_DEPRECATEDDESCRIPTION] = $deprecationDescription;
            }
            if ($args = $schemaDefinitionResolver->getSchemaFieldArgs($typeResolver, $fieldName)) {
                $schemaDefinition[SchemaDefinition::ARGNAME_ARGS] = $args;
            }
        }
        if (!is_null($this->resolveFieldDefaultDataloaderClass($typeResolver, $fieldName, $fieldArgs))) {
            $schemaDefinition[SchemaDefinition::ARGNAME_RELATIONAL] = true;
        }
        $this->addSchemaDefinitionForField($schemaDefinition, $fieldName);
        return $schemaDefinition;
    }

    public function enableOrderedSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        return true;
    }

    public function resolveSchemaValidationWarningDescription(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        return null;
    }

    /**
     * Function to override
     */
    protected function addSchemaDefinitionForField(array &$schemaDefinition, string $fieldName)
    {
    }

    protected function getFieldArgumentsSchemaDefinitions(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): array
    {
        if ($filterDataloadingModule = $this->getFieldDefaultFilterDataloadingModule($typeResolver, $fieldName, $fieldArgs)) {
            $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
            $filterqueryargs_modules = $moduleprocessor_manager->getProcessor($filterDataloadingModule)->getDataloadQueryArgsFilteringModules($filterDataloadingModule);
            return GeneralUtils::arrayFlatten(array_map(function($module) use($moduleprocessor_manager) {
                return $moduleprocessor_manager->getProcessor($module)->getFilterInputSchemaDefinitionItems($module);
            }, $filterqueryargs_modules));
        }

        return [];
    }

    public function resolveCanProcessResultItem(TypeResolverInterface $typeResolver, $resultItem, string $fieldName, array $fieldArgs = []): bool
    {
        return true;
    }

    protected function getValidationCheckpoints(TypeResolverInterface $typeResolver, $resultItem, string $fieldName, array $fieldArgs = []): ?array
    {
        return null;
    }

    protected function getValidationCheckpointsErrorMessage(TypeResolverInterface $typeResolver, $resultItem, string $fieldName, array $fieldArgs = []): ?string
    {
        return null;
    }

    public function getValidationErrorDescription(TypeResolverInterface $typeResolver, $resultItem, string $fieldName, array $fieldArgs = []): ?string
    {
        // Can perform validation through checkpoints
        if ($checkpoints = $this->getValidationCheckpoints($typeResolver, $resultItem, $fieldName, $fieldArgs)) {
            $engine = EngineFacade::getInstance();
            $validation = $engine->validateCheckpoints($checkpoints);
            if (\PoP\ComponentModel\GeneralUtils::isError($validation)) {
                // Check if there is a custom error message
                $message = $this->getValidationCheckpointsErrorMessage($typeResolver, $resultItem, $fieldName, $fieldArgs);
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

    public function resolveValue(TypeResolverInterface $typeResolver, $resultItem, string $fieldName, array $fieldArgs = [], ?array $variables = null, ?array $expressions = null, array $options = [])
    {
        return null;
    }

    public function resolveFieldDefaultDataloaderClass(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        return null;
    }

    protected function getFieldDefaultFilterDataloadingModule(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?array
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $dataloaderClass = $this->resolveFieldDefaultDataloaderClass($typeResolver, $fieldName, $fieldArgs);
        $dataloader = $instanceManager->getInstance($dataloaderClass);
        return $dataloader->getFilterDataloadingModule();
    }

    protected function addFilterDataloadQueryArgs(array &$options, TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = [])
    {
        $options['filter-dataload-query-args'] = [
            'source' => $fieldArgs,
            'module' => $this->getFieldDefaultFilterDataloadingModule($typeResolver, $fieldName, $fieldArgs),
        ];
    }
}
