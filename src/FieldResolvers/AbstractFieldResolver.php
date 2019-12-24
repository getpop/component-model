<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\FieldQuery\FieldQueryUtils;
use PoP\ComponentModel\Schema\SchemaHelpers;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\Facades\Engine\EngineFacade;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\FieldResolvers\SchemaDefinitionResolverTrait;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionTrait;
use PoP\ComponentModel\FieldResolvers\FieldSchemaDefinitionResolverInterface;

abstract class AbstractFieldResolver implements FieldResolverInterface, FieldSchemaDefinitionResolverInterface
{
    /**
     * This class is attached to a TypeResolver
     */
    use AttachableExtensionTrait;
    use SchemaDefinitionResolverTrait;

    public static function getImplementedInterfaceClasses(): array
    {
        return [];
    }

    /**
     * Implement all the fieldNames defined in the interfaces
     *
     * @return array
     */
    public static function getFieldNamesFromInterfaces(): array
    {
        $fieldNames = [];

        // Iterate classes from the current class towards the parent classes until finding typeResolver that satisfies processing this field
        foreach (self::getInterfaceClasses() as $interfaceClass) {
            $fieldNames = array_merge(
                $fieldNames,
                $interfaceClass::getFieldNamesToImplement()
            );
        }

        return array_values(array_unique($fieldNames));
    }

    public function isGlobal(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        return false;
    }

    /**
     * Implement all the fieldNames defined in the interfaces
     *
     * @return array
     */
    public static function getInterfaceClasses(): array
    {
        $interfaces = [];

        // Iterate classes from the current class towards the parent classes until finding typeResolver that satisfies processing this field
        $class = get_called_class();
        do {
            $interfaces = array_merge(
                $interfaces,
                $class::getImplementedInterfaceClasses()
            );
            // Otherwise, continue iterating for the class parents
        } while ($class = get_parent_class($class));

        return array_values(array_unique($interfaces));
    }

    /**
     * Indicates if the fieldResolver can process this combination of fieldName and fieldArgs
     * It is required to support a multiverse of fields: different fieldResolvers can resolve the field, based on the required version (passed through $fieldArgs['branch'])
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
        $fieldSchemaDefinition = $this->getSchemaDefinitionForField($typeResolver, $fieldName, $fieldArgs);
        if ($schemaFieldArgs = $fieldSchemaDefinition[SchemaDefinition::ARGNAME_ARGS]) {
            // Iterate all the mandatory fieldArgs and, if they are not present, throw an error
            if ($mandatoryArgs = SchemaHelpers::getSchemaMandatoryFieldArgs($schemaFieldArgs)) {
                if ($maybeError = $this->validateNotMissingFieldArguments(
                    SchemaHelpers::getSchemaFieldArgNames($mandatoryArgs),
                    $fieldName,
                    $fieldArgs
                )) {
                    return $maybeError;
                }
            }

            // Important: The validations below can only be done if no fieldArg contains a field!
            // That is because this is a schema error, so we still don't have the $resultItem against which to resolve the field
            // For instance, this doesn't work: /?query=arrayItem(posts(),3)
            // In that case, the validation will be done inside ->resolveValue(), and will be treated as a $dbError, not a $schemaError
            if (!FieldQueryUtils::isAnyFieldArgumentValueAField($schemaFieldArgs)) {
                // Iterate all the enum types and check that the provided values is one of them, or throw an error
                if ($enumArgs = SchemaHelpers::getSchemaEnumTypeFieldArgs($schemaFieldArgs)) {
                    if ($maybeError = $this->validateEnumFieldArguments(
                        $enumArgs,
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

    protected function validateEnumFieldArguments(array $enumArgs, string $fieldName, array $fieldArgs = []): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $errors = [];
        $fieldArgumentNames = SchemaHelpers::getSchemaFieldArgNames($enumArgs);
        $fieldArgumentEnumValues = SchemaHelpers::getSchemaFieldArgEnumValues($enumArgs);
        for ($i=0; $i<count($fieldArgumentNames); $i++) {
            $fieldArgumentName = $fieldArgumentNames[$i];
            $fieldArgumentEnumValues = $fieldArgumentEnumValues[$i];
            $fieldArgumentValue = $fieldArgs[$fieldArgumentName];
            // If the field is mandatory and not set, the "mandatory" validation above will fail.
            // Here only validate if the field value is provided
            if (!is_null($fieldArgumentValue) && !in_array($fieldArgumentValue, $fieldArgumentEnumValues)) {
                $errors[] = sprintf(
                    $translationAPI->__('Value \'%s\' for argument \'%s\' is not allowed (the only allowed values are: \'%s\')', 'component-model'),
                    $fieldArgumentValue,
                    $fieldArgumentName,
                    implode($translationAPI->__('\', \''), $fieldArgumentEnumValues)
                );
            }
        }
        if ($errors) {
            return implode($translationAPI->__('. '), $errors);
        }
        return null;
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
        // Find which is the $schemaDefinitionResolver that will satisfy this schema definition
        // First try the one declared by the fieldResolver
        $maybeSchemaDefinitionResolver = $this->getSchemaDefinitionResolver($typeResolver);
        if (!is_null($maybeSchemaDefinitionResolver) && in_array($fieldName, $maybeSchemaDefinitionResolver::getFieldNamesToResolve())) {
            $schemaDefinitionResolver = $maybeSchemaDefinitionResolver;
        } else {
            // Otherwise, try through all of its interfaces
            $instanceManager = InstanceManagerFacade::getInstance();
            foreach (self::getInterfaceClasses() as $interfaceClass) {
                if (in_array($fieldName, $interfaceClass::getFieldNamesToImplement())) {
                    $schemaDefinitionResolver = $instanceManager->getInstance($interfaceClass);
                    break;
                }
            }
        }

        // If we found a resolver for this fieldName, get all its properties from it
        if ($schemaDefinitionResolver) {
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
                // Add the args under their name
                $nameArgs = [];
                foreach ($args as $arg) {
                    $nameArgs[$arg[SchemaDefinition::ARGNAME_NAME]] = $arg;
                }
                $schemaDefinition[SchemaDefinition::ARGNAME_ARGS] = $nameArgs;
            }
            $schemaDefinitionResolver->addSchemaDefinitionForField($schemaDefinition, $typeResolver, $fieldName);
        }
        if (!is_null($this->resolveFieldTypeResolverClass($typeResolver, $fieldName, $fieldArgs))) {
            $schemaDefinition[SchemaDefinition::ARGNAME_RELATIONAL] = true;
        }
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

    protected function getFieldArgumentsSchemaDefinitions(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): array
    {
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

    public function resolveFieldTypeResolverClass(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        return null;
    }
}
