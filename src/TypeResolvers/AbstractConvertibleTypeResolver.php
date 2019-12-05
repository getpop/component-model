<?php
namespace PoP\ComponentModel\TypeResolvers;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\Facades\AttachableExtensions\AttachableExtensionManagerFacade;

abstract class AbstractConvertibleTypeResolver extends AbstractTypeResolver
{
    protected $typeResolverPickers;

    abstract protected function getBaseTypeResolverClass(): string;

    public function getFieldNamesToResolve(): array
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // The only FieldNames we can always guarantee are those from the base class
        // The others depend on the resultItem, to see if they satisfy the specific resolver condition
        if ($baseTypeResolverClass = $this->getBaseTypeResolverClass()) {
            $typeResolver = $instanceManager->getInstance($baseTypeResolverClass);
            return $typeResolver->getFieldNamesToResolve();
        }

        return parent::getFieldNamesToResolve();
    }

    public function getDirectiveNameClasses(): array
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        if ($baseTypeResolverClass = $this->getBaseTypeResolverClass()) {
            $typeResolver = $instanceManager->getInstance($baseTypeResolverClass);
            return $typeResolver->getDirectiveNameClasses();
        }

        return parent::getFieldNamesToResolve();
    }

    public function hasFieldResolversForField(string $field): bool
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // The only FieldNames we can always guarantee are those from the base class
        // The others depend on the resultItem, to see if they satisfy the specific resolver condition
        if ($baseTypeResolverClass = $this->getBaseTypeResolverClass()) {
            $typeResolver = $instanceManager->getInstance($baseTypeResolverClass);
            return $typeResolver->hasFieldResolversForField($field);
        }

        return parent::hasFieldResolversForField($field);
    }

    public function getSchemaFieldArgs(string $field): array
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // The only FieldNames we can always guarantee are those from the base class
        // The others depend on the resultItem, to see if they satisfy the specific resolver condition
        if ($baseTypeResolverClass = $this->getBaseTypeResolverClass()) {
            $typeResolver = $instanceManager->getInstance($baseTypeResolverClass);
            return $typeResolver->getSchemaFieldArgs($field);
        }

        return parent::getSchemaFieldArgs($field);
    }

    public function enableOrderedSchemaFieldArgs(string $field): bool
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // The only FieldNames we can always guarantee are those from the base class
        // The others depend on the resultItem, to see if they satisfy the specific resolver condition
        if ($baseTypeResolverClass = $this->getBaseTypeResolverClass()) {
            $typeResolver = $instanceManager->getInstance($baseTypeResolverClass);
            return $typeResolver->enableOrderedSchemaFieldArgs($field);
        }

        return parent::enableOrderedSchemaFieldArgs($typeResolver, $field);
    }

    protected function getTypeResolverPickers()
    {
        if (is_null($this->typeResolverPickers)) {
            $this->typeResolverPickers = $this->calculateTypeResolverPickers();
        }
        return $this->typeResolverPickers;
    }

    protected function calculateTypeResolverPickers()
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();

        // Iterate classes from the current class towards the parent classes until finding typeResolver that satisfies processing this field
        $class = get_called_class();
        $pickers = [];
        do {
            // All the pickers and their priorities for this class level
            // Important: do array_reverse to enable more specific hooks, which are initialized later on in the project, to be the chosen ones (if their priority is the same)
            $extensionPickerClassPriorities = array_reverse($attachableExtensionManager->getExtensionClasses($class, \PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups::TYPERESOLVERPICKERS));
            $classPickerPriorities = array_values($extensionPickerClassPriorities);
            $classPickerClasses = array_keys($extensionPickerClassPriorities);
            $classPickers = array_map(function($extensionClass) use($instanceManager) {
                return $instanceManager->getInstance($extensionClass);
            }, $classPickerClasses);

            // Sort the found pickers by their priority, and then add to the stack of all pickers, for all classes
            // Higher priority means they execute first!
            array_multisort($classPickerPriorities, SORT_DESC, SORT_NUMERIC, $classPickers);
            $pickers = array_merge(
                $pickers,
                $classPickers
            );
            // Continue iterating for the class parents
        } while ($class = get_parent_class($class));

        // Return all the pickers
        return $pickers;
    }

    protected function getTypeResolverAndPicker($resultItem)
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // Among all registered fieldresolvers, check if any is able to process the object, through function `process`
        // Important: iterate from back to front, because more general components (eg: Users) are defined first,
        // and dependent components (eg: Communities, Organizations) are defined later
        // Then, more specific implementations (eg: Organizations) must be queried before more general ones (eg: Communities)
        // This is not a problem by making the corresponding field processors inherit from each other, so that the more specific object also handles
        // the fields for the more general ones (eg: TypeResolver_OrganizationUsers extends TypeResolver_CommunityUsers, and TypeResolver_CommunityUsers extends TypeResolver_Users)
        foreach ($this->getTypeResolverPickers() as $maybePicker) {

            if ($maybePicker->process($resultItem)) {
                // Found it!
                $typeResolverPicker = $maybePicker;
                $typeResolverClass = $typeResolverPicker->getTypeResolverClass();
                break;
            }
        }

        // If none found, use the default one
        $typeResolverClass = $typeResolverClass ?? $this->getBaseTypeResolverClass();

        // From the typeResolver name, return the object
        $typeResolver = $instanceManager->getInstance($typeResolverClass);

        // Return also the resolver, as to cast the object
        return array($typeResolver, $typeResolverPicker);
    }

    public function resolveValue($resultItem, string $field, ?array $variables = null, ?array $expressions = null, array $options = [])
    {
        // Delegate to the TypeResolver corresponding to this object
        list($typeResolver, $fieldresolverpicker) = $this->getTypeResolverAndPicker($resultItem);

        // Cast object, eg from post to event
        if ($fieldresolverpicker) {
            $resultItem = $fieldresolverpicker->cast($resultItem);
        }

        // Delegate to that typeResolver to obtain the value
        return $typeResolver->resolveValue($resultItem, $field, $variables, $expressions, $options);
    }

    protected function addSchemaDefinition(array $fieldArgs = [], array $options = [])
    {
        $instanceManager = InstanceManagerFacade::getInstance();

        $this->schemaDefinition[SchemaDefinition::ARGNAME_CONVERTIBLE] = true;

        // Default typeResolver, under "base" condition
        $baseFields = [];
        if ($baseTypeResolverClass = $this->getBaseTypeResolverClass()) {
            $typeResolver = $instanceManager->getInstance($baseTypeResolverClass);
            $this->schemaDefinition[SchemaDefinition::ARGNAME_BASERESOLVER] = $typeResolver->getSchemaDefinition($fieldArgs, $options);
            $baseFields = array_map(function($fieldProps) {
                return $fieldProps[SchemaDefinition::ARGNAME_NAME];
            }, (array)$this->schemaDefinition[SchemaDefinition::ARGNAME_BASERESOLVER][SchemaDefinition::ARGNAME_FIELDS]);
        }

        // Iterate through the typeResolvers from all the pickers and get their schema definitions, under their object nature
        foreach ($this->getTypeResolverPickers() as $picker) {
            $typeResolver = $instanceManager->getInstance($picker->getTypeResolverClass());
            // Do not repeat those fields already present on the base typeResolver
            $deltaFields = $typeResolver->getSchemaDefinition($fieldArgs, $options);
            $deltaFields[SchemaDefinition::ARGNAME_FIELDS] = array_values(array_filter(
                $deltaFields[SchemaDefinition::ARGNAME_FIELDS],
                function($fieldProps) use($baseFields) {
                    return !in_array($fieldProps[SchemaDefinition::ARGNAME_NAME], $baseFields);
                }
            ));
            $this->schemaDefinition[SchemaDefinition::ARGNAME_RESOLVERSBYOBJECTNATURE][$picker->getSchemaDefinitionObjectNature()] = $deltaFields;
        }
    }

    public function resolveSchemaValidationErrorDescriptions(string $field, array &$variables = null): ?array
    {
        $fieldName = FieldQueryInterpreterFacade::getInstance()->getFieldName($field);
        $instanceManager = InstanceManagerFacade::getInstance();

        // Default typeResolver, under "base" condition
        if ($baseTypeResolverClass = $this->getBaseTypeResolverClass()) {
            $typeResolver = $instanceManager->getInstance($baseTypeResolverClass);
            if (in_array($fieldName, $typeResolver->getFieldNamesToResolve())) {
                return $typeResolver->resolveSchemaValidationErrorDescriptions($field, $variables);
            }
        }

        // Iterate through the typeResolvers from all the pickers and get their docucumentation, under their object nature
        foreach ($this->getTypeResolverPickers() as $picker) {
            $typeResolver = $instanceManager->getInstance($picker->getTypeResolverClass());
            if (in_array($fieldName, $typeResolver->getFieldNamesToResolve())) {
                return $typeResolver->resolveSchemaValidationErrorDescriptions($field, $variables);
            }
        }
        return parent::resolveSchemaValidationErrorDescriptions($field, $variables);
    }

    public function resolveSchemaValidationWarningDescriptions(string $field, array &$variables = null): array
    {
        $fieldName = FieldQueryInterpreterFacade::getInstance()->getFieldName($field);
        $instanceManager = InstanceManagerFacade::getInstance();

        // Default typeResolver, under "base" condition
        if ($baseTypeResolverClass = $this->getBaseTypeResolverClass()) {
            $typeResolver = $instanceManager->getInstance($baseTypeResolverClass);
            if (in_array($fieldName, $typeResolver->getFieldNamesToResolve())) {
                return $typeResolver->resolveSchemaValidationWarningDescriptions($field, $variables);
            }
        }

        // Iterate through the typeResolvers from all the pickers and get their docucumentation, under their object nature
        foreach ($this->getTypeResolverPickers() as $picker) {
            $typeResolver = $instanceManager->getInstance($picker->getTypeResolverClass());
            if (in_array($fieldName, $typeResolver->getFieldNamesToResolve())) {
                return $typeResolver->resolveSchemaValidationWarningDescriptions($field, $variables);
            }
        }
        return parent::resolveSchemaValidationWarningDescriptions($field, $variables);
    }

    public function resolveSchemaDeprecationDescriptions(string $field, array &$variables = null): array
    {
        $fieldName = FieldQueryInterpreterFacade::getInstance()->getFieldName($field);
        $instanceManager = InstanceManagerFacade::getInstance();

        // Default typeResolver, under "base" condition
        if ($baseTypeResolverClass = $this->getBaseTypeResolverClass()) {
            $typeResolver = $instanceManager->getInstance($baseTypeResolverClass);
            if (in_array($fieldName, $typeResolver->getFieldNamesToResolve())) {
                return $typeResolver->resolveSchemaDeprecationDescriptions($field, $variables);
            }
        }

        // Iterate through the typeResolvers from all the pickers and get their docucumentation, under their object nature
        foreach ($this->getTypeResolverPickers() as $picker) {
            $typeResolver = $instanceManager->getInstance($picker->getTypeResolverClass());
            if (in_array($fieldName, $typeResolver->getFieldNamesToResolve())) {
                return $typeResolver->resolveSchemaDeprecationDescriptions($field, $variables);
            }
        }
        return parent::resolveSchemaDeprecationDescriptions($field, $variables);
    }

    public function resolveFieldDefaultDataloaderClass(string $field): ?string
    {
        $fieldName = FieldQueryInterpreterFacade::getInstance()->getFieldName($field);
        $instanceManager = InstanceManagerFacade::getInstance();

        // Default typeResolver, under "base" condition
        if ($baseTypeResolverClass = $this->getBaseTypeResolverClass()) {
            $typeResolver = $instanceManager->getInstance($baseTypeResolverClass);
            if (in_array($fieldName, $typeResolver->getFieldNamesToResolve())) {
                return $typeResolver->resolveFieldDefaultDataloaderClass($field);
            }
        }

        // Iterate through the typeResolvers from all the pickers and get their docucumentation, under their object nature
        foreach ($this->getTypeResolverPickers() as $picker) {
            $typeResolver = $instanceManager->getInstance($picker->getTypeResolverClass());
            if (in_array($fieldName, $typeResolver->getFieldNamesToResolve())) {
                return $typeResolver->resolveFieldDefaultDataloaderClass($field);
            }
        }

        return parent::resolveFieldDefaultDataloaderClass($field);
    }
}