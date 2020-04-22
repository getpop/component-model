<?php

declare(strict_types=1);

namespace PoP\ComponentModel\FieldResolvers;

use Exception;
use Composer\Semver\Semver;
use PoP\ComponentModel\Environment;
use PoP\ComponentModel\Misc\GeneralUtils;
use PoP\ComponentModel\Schema\SchemaHelpers;
use PoP\ComponentModel\Schema\FieldQueryUtils;
use PoP\ComponentModel\State\ApplicationState;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\Facades\Engine\EngineFacade;
use PoP\ComponentModel\Versioning\VersioningHelpers;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\Resolvers\FieldOrDirectiveResolverTrait;
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
    use FieldOrDirectiveResolverTrait;

    protected $enumValueArgumentValidationCache = [];
    protected $schemaDefinitionForFieldCache = [];

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
     * Define if to use the version to decide if to process the field or not
     *
     * @param TypeResolverInterface $typeResolver
     * @return boolean
     */
    public function decideCanProcessBasedOnVersionConstraint(TypeResolverInterface $typeResolver): bool
    {
        return false;
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
        /** Check if to validate the version */
        if (Environment::enableSemanticVersionConstraints() &&
            $this->decideCanProcessBasedOnVersionConstraint($typeResolver)
        ) {
            /**
             * Please notice: we can get the fieldVersion directly from this instance,
             * and not from the schemaDefinition, because the version is set at the FieldResolver level,
             * and not the FieldInterfaceResolver, which is the other entity filling data
             * inside the schemaDefinition object.
             * If this field is tagged with a version...
             */
            if ($schemaFieldVersion = $this->getSchemaFieldVersion($typeResolver, $fieldName)) {
                $vars = ApplicationState::getVars();
                /**
                 * Get versionConstraint in this order:
                 * 1. Passed as field argument
                 * 2. Through param `fieldVersionConstraints[$fieldName]`: specific to the namespaced type + field
                 * 3. Through param `fieldVersionConstraints[$fieldName]`: specific to the type + field
                 * 4. Through param `versionConstraint`: applies to all fields and directives in the query
                 */
                $versionConstraint =
                    $fieldArgs[SchemaDefinition::ARGNAME_VERSION_CONSTRAINT]
                    ?? VersioningHelpers::getVersionConstraintsForField(
                        $typeResolver->getNamespacedTypeName(),
                        $fieldName
                    )
                    ?? VersioningHelpers::getVersionConstraintsForField(
                        $typeResolver->getTypeName(),
                        $fieldName
                    )
                    ?? $vars['version-constraint'];
                /**
                 * If the query doesn't restrict the version, then do not process
                 */
                if (!$versionConstraint) {
                    return false;
                }
                /**
                 * Compare using semantic versioning constraint rules, as used by Composer
                 * If passing a wrong value to validate against (eg: "saraza" instead of "1.0.0"), it will throw an Exception
                 */
                try {
                    return Semver::satisfies($schemaFieldVersion, $versionConstraint);
                } catch (Exception $e) {
                    return false;
                }
            }
        }
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
            if (!FieldQueryUtils::isAnyFieldArgumentValueAField($fieldArgs)) {
                // Iterate all the enum types and check that the provided values is one of them, or throw an error
                if ($enumArgs = SchemaHelpers::getSchemaEnumTypeFieldArgs($schemaFieldArgs)) {
                    list(
                        $maybeError,
                    ) = $this->validateEnumFieldArguments(
                        $enumArgs,
                        $fieldName,
                        $fieldArgs
                    );
                    if ($maybeError) {
                        return $maybeError;
                    }
                }
            }
        }
        return null;
    }
    public function resolveSchemaValidationDeprecationDescription(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        $fieldSchemaDefinition = $this->getSchemaDefinitionForField($typeResolver, $fieldName, $fieldArgs);
        if ($schemaFieldArgs = $fieldSchemaDefinition[SchemaDefinition::ARGNAME_ARGS]) {
            // Important: The validations below can only be done if no fieldArg contains a field!
            // That is because this is a schema error, so we still don't have the $resultItem against which to resolve the field
            // For instance, this doesn't work: /?query=arrayItem(posts(),3)
            // In that case, the validation will be done inside ->resolveValue(), and will be treated as a $dbError, not a $schemaError
            if (!FieldQueryUtils::isAnyFieldArgumentValueAField($fieldArgs)) {
                // Iterate all the enum types and check that the provided values is one of them, or throw an error
                if ($enumArgs = SchemaHelpers::getSchemaEnumTypeFieldArgs($schemaFieldArgs)) {
                    list(
                        $maybeError,
                        $maybeDeprecation
                    ) = $this->validateEnumFieldArguments(
                        $enumArgs,
                        $fieldName,
                        $fieldArgs
                    );
                    if ($maybeDeprecation) {
                        return $maybeDeprecation;
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

    /**
     * Get the "schema" properties as for the fieldName
     *
     * @return array
     */
    public function getSchemaDefinitionForField(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): array
    {
        // First check if the value was cached
        $key = $typeResolver->getNamespacedTypeName() . '|' . $fieldName . '|' . json_encode($fieldArgs);
        if (is_null($this->schemaDefinitionForFieldCache[$key])) {
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
                    $schemaDefinition[SchemaDefinition::ARGNAME_DEPRECATIONDESCRIPTION] = $deprecationDescription;
                }
                if ($args = $schemaDefinitionResolver->getFilteredSchemaFieldArgs($typeResolver, $fieldName)) {
                    // Add the args under their name
                    $nameArgs = [];
                    foreach ($args as $arg) {
                        $nameArgs[$arg[SchemaDefinition::ARGNAME_NAME]] = $arg;
                    }
                    $schemaDefinition[SchemaDefinition::ARGNAME_ARGS] = $nameArgs;
                }
                $schemaDefinitionResolver->addSchemaDefinitionForField($schemaDefinition, $typeResolver, $fieldName);
            }
            /**
             * Please notice: the version always comes from the fieldResolver, and not from the schemaDefinitionResolver
             * That is because it is the implementer the one who knows what version it is, and not the one defining the interface
             * If the interface changes, the implementer will need to change, so the version will be upgraded
             * But it could also be that the contract doesn't change, but the implementation changes
             * In particular, Interfaces are schemaDefinitionResolver, but they must not indicate the version...
             * it's really not their responsibility
             */
            if (Environment::enableSemanticVersionConstraints()) {
                if ($version = $this->getSchemaFieldVersion($typeResolver, $fieldName)) {
                    $schemaDefinition[SchemaDefinition::ARGNAME_VERSION] = $version;
                }
            }
            if (!is_null($this->resolveFieldTypeResolverClass($typeResolver, $fieldName, $fieldArgs))) {
                $schemaDefinition[SchemaDefinition::ARGNAME_RELATIONAL] = true;
            }
            $this->schemaDefinitionForFieldCache[$key] = $schemaDefinition;
        }
        return $this->schemaDefinitionForFieldCache[$key];
    }

    public function enableOrderedSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        return true;
    }

    public function getSchemaFieldVersion(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        return null;
    }

    protected function hasSchemaFieldVersion(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        return !empty($this->getSchemaFieldVersion($typeResolver, $fieldName));
    }

    public function resolveSchemaValidationWarningDescription(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        if (Environment::enableSemanticVersionConstraints()) {
            /**
             * If restricting the version, and this fieldResolver doesn't have any version, then show a warning
             */
            if ($versionConstraint = $fieldArgs[SchemaDefinition::ARGNAME_VERSION_CONSTRAINT]) {
                /**
                 * If this fieldResolver doesn't have versioning, then it accepts everything
                 */
                if (!$this->decideCanProcessBasedOnVersionConstraint($typeResolver)) {
                    $translationAPI = TranslationAPIFacade::getInstance();
                    return sprintf(
                        $translationAPI->__('The FieldResolver used to process field with name \'%s\' (which has version \'%s\') does not pay attention to the version constraint; hence, argument \'versionConstraint\', with value \'%s\', was ignored', 'component-model'),
                        $fieldName,
                        $this->getSchemaFieldVersion($typeResolver, $fieldName) ?? '',
                        $versionConstraint
                    );
                }
            }
        }
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
            if (GeneralUtils::isError($validation)) {
                // Check if there is a custom error message
                $message = $this->getValidationCheckpointsErrorMessage($typeResolver, $resultItem, $fieldName, $fieldArgs);
                if (is_null($message)) {
                    // Return a generic message
                    $error = $validation;
                    $translationAPI = TranslationAPIFacade::getInstance();
                    return $error->getErrorMessage() ?
                        $error->getErrorMessage() :
                        sprintf(
                            $translationAPI->__('Validation with code \'%s\' failed', 'component-model'),
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
