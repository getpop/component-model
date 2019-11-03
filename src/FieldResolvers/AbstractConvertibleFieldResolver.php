<?php
namespace PoP\ComponentModel\FieldResolvers;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\Facades\AttachableExtensions\AttachableExtensionManagerFacade;

abstract class AbstractConvertibleFieldResolver extends AbstractFieldResolver
{
    protected $fieldResolverPickers;

    abstract protected function getBaseFieldResolverClass(): string;

    public function getFieldNamesToResolve(): array
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // The only FieldNames we can always guarantee are those from the base class
        // The others depend on the resultItem, to see if they satisfy the specific resolver condition
        if ($baseFieldResolverClass = $this->getBaseFieldResolverClass()) {
            $fieldResolver = $instanceManager->getInstance($baseFieldResolverClass);
            return $fieldResolver->getFieldNamesToResolve();
        }

        return parent::getFieldNamesToResolve();
    }

    public function getDirectiveNameClasses(): array
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        if ($baseFieldResolverClass = $this->getBaseFieldResolverClass()) {
            $fieldResolver = $instanceManager->getInstance($baseFieldResolverClass);
            return $fieldResolver->getDirectiveNameClasses();
        }

        return parent::getFieldNamesToResolve();
    }

    public function hasFieldValueResolversForField(string $field): bool
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // The only FieldNames we can always guarantee are those from the base class
        // The others depend on the resultItem, to see if they satisfy the specific resolver condition
        if ($baseFieldResolverClass = $this->getBaseFieldResolverClass()) {
            $fieldResolver = $instanceManager->getInstance($baseFieldResolverClass);
            return $fieldResolver->hasFieldValueResolversForField($field);
        }

        return parent::hasFieldValueResolversForField($field);
    }

    public function getSchemaFieldArgs(string $field): array
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // The only FieldNames we can always guarantee are those from the base class
        // The others depend on the resultItem, to see if they satisfy the specific resolver condition
        if ($baseFieldResolverClass = $this->getBaseFieldResolverClass()) {
            $fieldResolver = $instanceManager->getInstance($baseFieldResolverClass);
            return $fieldResolver->getSchemaFieldArgs($field);
        }

        return parent::getSchemaFieldArgs($field);
    }

    public function enableOrderedSchemaFieldArgs(string $field): bool
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // The only FieldNames we can always guarantee are those from the base class
        // The others depend on the resultItem, to see if they satisfy the specific resolver condition
        if ($baseFieldResolverClass = $this->getBaseFieldResolverClass()) {
            $fieldResolver = $instanceManager->getInstance($baseFieldResolverClass);
            return $fieldResolver->enableOrderedSchemaFieldArgs($field);
        }

        return parent::enableOrderedSchemaFieldArgs($fieldResolver, $field);
    }

    protected function getFieldResolverPickers()
    {
        if (is_null($this->fieldResolverPickers)) {
            $this->fieldResolverPickers = $this->calculateFieldResolverPickers();
        }
        return $this->fieldResolverPickers;
    }

    protected function calculateFieldResolverPickers()
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $attachableExtensionManager = AttachableExtensionManagerFacade::getInstance();

        // Iterate classes from the current class towards the parent classes until finding fieldResolver that satisfies processing this field
        $class = get_called_class();
        $pickers = [];
        do {
            // All the pickers and their priorities for this class level
            // Important: do array_reverse to enable more specific hooks, which are initialized later on in the project, to be the chosen ones (if their priority is the same)
            $extensionPickerClassPriorities = array_reverse($attachableExtensionManager->getExtensionClasses($class, \PoP\ComponentModel\AttachableExtensions\AttachableExtensionGroups::FIELDRESOLVERPICKERS));
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

    protected function getFieldResolverAndPicker($resultItem)
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // Among all registered fieldvalueresolvers, check if any is able to process the object, through function `process`
        // Important: iterate from back to front, because more general components (eg: Users) are defined first,
        // and dependent components (eg: Communities, Organizations) are defined later
        // Then, more specific implementations (eg: Organizations) must be queried before more general ones (eg: Communities)
        // This is not a problem by making the corresponding field processors inherit from each other, so that the more specific object also handles
        // the fields for the more general ones (eg: FieldResolver_OrganizationUsers extends FieldResolver_CommunityUsers, and FieldResolver_CommunityUsers extends FieldResolver_Users)
        foreach ($this->getFieldResolverPickers() as $maybePicker) {

            if ($maybePicker->process($resultItem)) {
                // Found it!
                $fieldResolverPicker = $maybePicker;
                $fieldResolverClass = $fieldResolverPicker->getFieldResolverClass();
                break;
            }
        }

        // If none found, use the default one
        $fieldResolverClass = $fieldResolverClass ?? $this->getBaseFieldResolverClass();

        // From the fieldResolver name, return the object
        $fieldResolver = $instanceManager->getInstance($fieldResolverClass);

        // Return also the resolver, as to cast the object
        return array($fieldResolver, $fieldResolverPicker);
    }

    public function resolveValue($resultItem, string $field)
    {
        // Delegate to the FieldResolver corresponding to this object
        list($fieldResolver, $fieldvalueresolverpicker) = $this->getFieldResolverAndPicker($resultItem);

        // Cast object, eg from post to event
        if ($fieldvalueresolverpicker) {
            $resultItem = $fieldvalueresolverpicker->cast($resultItem);
        }

        // Delegate to that fieldResolver to obtain the value
        return $fieldResolver->resolveValue($resultItem, $field);
    }

    protected function addSchemaDefinition(array $fieldArgs = [], array $options = [])
    {
        $instanceManager = InstanceManagerFacade::getInstance();

        $this->schemaDefinition[SchemaDefinition::ARGNAME_CONVERTIBLE] = true;

        // Default fieldResolver, under "base" condition
        $baseFields = [];
        if ($baseFieldResolverClass = $this->getBaseFieldResolverClass()) {
            $fieldResolver = $instanceManager->getInstance($baseFieldResolverClass);
            $this->schemaDefinition[SchemaDefinition::ARGNAME_BASERESOLVER] = $fieldResolver->getSchemaDefinition($fieldArgs, $options);
            $baseFields = array_map(function($fieldProps) {
                return $fieldProps[SchemaDefinition::ARGNAME_NAME];
            }, (array)$this->schemaDefinition[SchemaDefinition::ARGNAME_BASERESOLVER][SchemaDefinition::ARGNAME_FIELDS]);
        }

        // Iterate through the fieldResolvers from all the pickers and get their schema definitions, under their object nature
        foreach ($this->getFieldResolverPickers() as $picker) {
            $fieldResolver = $instanceManager->getInstance($picker->getFieldResolverClass());
            // Do not repeat those fields already present on the base fieldResolver
            $deltaFields = $fieldResolver->getSchemaDefinition($fieldArgs, $options);
            $deltaFields[SchemaDefinition::ARGNAME_FIELDS] = array_values(array_filter(
                $deltaFields[SchemaDefinition::ARGNAME_FIELDS],
                function($fieldProps) use($baseFields) {
                    return !in_array($fieldProps[SchemaDefinition::ARGNAME_NAME], $baseFields);
                }
            ));
            $this->schemaDefinition[SchemaDefinition::ARGNAME_RESOLVERSBYOBJECTNATURE][$picker->getSchemaDocumentationObjectNature()] = $deltaFields;
        }
    }

    public function resolveSchemaValidationErrorDescriptions(string $field): ?array
    {
        $fieldName = FieldQueryInterpreterFacade::getInstance()->getFieldName($field);
        $instanceManager = InstanceManagerFacade::getInstance();

        // Default fieldResolver, under "base" condition
        if ($baseFieldResolverClass = $this->getBaseFieldResolverClass()) {
            $fieldResolver = $instanceManager->getInstance($baseFieldResolverClass);
            if (in_array($fieldName, $fieldResolver->getFieldNamesToResolve())) {
                return $fieldResolver->resolveSchemaValidationErrorDescriptions($field);
            }
        }

        // Iterate through the fieldResolvers from all the pickers and get their docucumentation, under their object nature
        foreach ($this->getFieldResolverPickers() as $picker) {
            $fieldResolver = $instanceManager->getInstance($picker->getFieldResolverClass());
            if (in_array($fieldName, $fieldResolver->getFieldNamesToResolve())) {
                return $fieldResolver->resolveSchemaValidationErrorDescriptions($field);
            }
        }
        return parent::resolveSchemaValidationErrorDescriptions($field);
    }

    public function resolveSchemaValidationWarningDescriptions(string $field): ?array
    {
        $fieldName = FieldQueryInterpreterFacade::getInstance()->getFieldName($field);
        $instanceManager = InstanceManagerFacade::getInstance();

        // Default fieldResolver, under "base" condition
        if ($baseFieldResolverClass = $this->getBaseFieldResolverClass()) {
            $fieldResolver = $instanceManager->getInstance($baseFieldResolverClass);
            if (in_array($fieldName, $fieldResolver->getFieldNamesToResolve())) {
                return $fieldResolver->resolveSchemaValidationWarningDescriptions($field);
            }
        }

        // Iterate through the fieldResolvers from all the pickers and get their docucumentation, under their object nature
        foreach ($this->getFieldResolverPickers() as $picker) {
            $fieldResolver = $instanceManager->getInstance($picker->getFieldResolverClass());
            if (in_array($fieldName, $fieldResolver->getFieldNamesToResolve())) {
                return $fieldResolver->resolveSchemaValidationWarningDescriptions($field);
            }
        }
        return parent::resolveSchemaValidationWarningDescriptions($field);
    }

    public function getSchemaDeprecationDescriptions(string $field): ?array
    {
        $fieldName = FieldQueryInterpreterFacade::getInstance()->getFieldName($field);
        $instanceManager = InstanceManagerFacade::getInstance();

        // Default fieldResolver, under "base" condition
        if ($baseFieldResolverClass = $this->getBaseFieldResolverClass()) {
            $fieldResolver = $instanceManager->getInstance($baseFieldResolverClass);
            if (in_array($fieldName, $fieldResolver->getFieldNamesToResolve())) {
                return $fieldResolver->getSchemaDeprecationDescriptions($field);
            }
        }

        // Iterate through the fieldResolvers from all the pickers and get their docucumentation, under their object nature
        foreach ($this->getFieldResolverPickers() as $picker) {
            $fieldResolver = $instanceManager->getInstance($picker->getFieldResolverClass());
            if (in_array($fieldName, $fieldResolver->getFieldNamesToResolve())) {
                return $fieldResolver->getSchemaDeprecationDescriptions($field);
            }
        }
        return parent::getSchemaDeprecationDescriptions($field);
    }

    public function resolveFieldDefaultDataloaderClass(string $field): ?string
    {
        $fieldName = FieldQueryInterpreterFacade::getInstance()->getFieldName($field);
        $instanceManager = InstanceManagerFacade::getInstance();

        // Default fieldResolver, under "base" condition
        if ($baseFieldResolverClass = $this->getBaseFieldResolverClass()) {
            $fieldResolver = $instanceManager->getInstance($baseFieldResolverClass);
            if (in_array($fieldName, $fieldResolver->getFieldNamesToResolve())) {
                return $fieldResolver->resolveFieldDefaultDataloaderClass($field);
            }
        }

        // Iterate through the fieldResolvers from all the pickers and get their docucumentation, under their object nature
        foreach ($this->getFieldResolverPickers() as $picker) {
            $fieldResolver = $instanceManager->getInstance($picker->getFieldResolverClass());
            if (in_array($fieldName, $fieldResolver->getFieldNamesToResolve())) {
                return $fieldResolver->resolveFieldDefaultDataloaderClass($field);
            }
        }

        return parent::resolveFieldDefaultDataloaderClass($field);
    }
}
