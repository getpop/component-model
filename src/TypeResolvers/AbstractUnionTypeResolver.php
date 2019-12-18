<?php
namespace PoP\ComponentModel\TypeResolvers;

use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\Facades\AttachableExtensions\AttachableExtensionManagerFacade;
use PoP\ComponentModel\Error;

abstract class AbstractUnionTypeResolver extends AbstractTypeResolver implements UnionTypeResolverInterface
{
    protected $typeResolverPickers;

    final public function getTypeOutputName(): string
    {
        return UnionTypeHelpers::getUnionTypeCollectionName(parent::getTypeOutputName());
    }

    /**
     * Remove the type from the ID to resolve the objects through `getObjects` (check parent class)
     *
     * @param array $ids_data_fields
     * @return void
     */
    protected function getIDsToQuery(array $ids_data_fields)
    {
        $ids = parent::getIDsToQuery($ids_data_fields);

        // Each ID contains the type (added in function `getId`). Remove it
        return array_map(
            [UnionTypeHelpers::class, 'extractDBObjectID'],
            $ids
        );
    }

    /**
     * Add the type to the ID
     *
     * @param [type] $resultItem
     * @return void
     */
    public function addTypeToID($resultItemID): string
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        if ($resultItemTypeResolverClass = $this->getTypeResolverClassForResultItem($resultItemID)) {
            $resultItemTypeResolver = $instanceManager->getInstance($resultItemTypeResolverClass);
            return UnionTypeHelpers::getDBObjectComposedTypeAndID(
                $resultItemTypeResolver,
                $resultItemID
            );
        }
        return (string)$resultItemID;
    }

    /**
     * In order to enable elements from different types (such as posts and users) to have same ID,
     * add the type to the ID
     *
     * @param [type] $resultItem
     * @return void
     */
    public function getId($resultItem)
    {
        $typeResolverAndPicker = $this->getTypeResolverAndPicker($resultItem);
        if (is_null($typeResolverAndPicker)) {
            return null;
        }

        list(
            $typeResolver,
        ) = $typeResolverAndPicker;

        // Add the type to the ID, so that elements of different types can live side by side
        // The type will be removed again in `getIDsToQuery`
        return UnionTypeHelpers::getDBObjectComposedTypeAndID(
            $typeResolver,
            $typeResolver->getId($resultItem)
        );
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

    public function getTypeResolverClassForResultItem($resultItemID)
    {
        // Among all registered fieldresolvers, check if any is able to process the object, through function `process`
        // Important: iterate from back to front, because more general components (eg: Users) are defined first,
        // and dependent components (eg: Communities, Organizations) are defined later
        // Then, more specific implementations (eg: Organizations) must be queried before more general ones (eg: Communities)
        // This is not a problem by making the corresponding field processors inherit from each other, so that the more specific object also handles
        // the fields for the more general ones (eg: TypeResolver_OrganizationUsers extends TypeResolver_CommunityUsers, and TypeResolver_CommunityUsers extends UserTypeResolver)
        foreach ($this->getTypeResolverPickers() as $maybePicker) {

            if ($maybePicker->process($resultItemID)) {
                // Found it!
                $typeResolverPicker = $maybePicker;
                return $typeResolverPicker->getTypeResolverClass();
            }
        }

        return null;
    }

    public function getTypeResolverAndPicker($resultItem)
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // Among all registered fieldresolvers, check if any is able to process the object, through function `process`
        // Important: iterate from back to front, because more general components (eg: Users) are defined first,
        // and dependent components (eg: Communities, Organizations) are defined later
        // Then, more specific implementations (eg: Organizations) must be queried before more general ones (eg: Communities)
        // This is not a problem by making the corresponding field processors inherit from each other, so that the more specific object also handles
        // the fields for the more general ones (eg: TypeResolver_OrganizationUsers extends TypeResolver_CommunityUsers, and TypeResolver_CommunityUsers extends UserTypeResolver)
        foreach ($this->getTypeResolverPickers() as $maybePicker) {

            if ($maybePicker->process($resultItem)) {
                // Found it!
                $typeResolverPicker = $maybePicker;
                $typeResolverClass = $typeResolverPicker->getTypeResolverClass();
                break;
            }
        }

        if ($typeResolverClass) {
            // From the typeResolver name, return the object
            $typeResolver = $instanceManager->getInstance($typeResolverClass);

            // Return also the resolver, as to cast the object
            return array($typeResolver, $typeResolverPicker);
        }
        return null;
    }

    protected function getUnresolvedResultItemIDError($resultItemID)
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return new Error(
            'unresolved-resultitem-id',
            sprintf(
                $translationAPI->__('Either the DataLoader can\'t load data, or no TypeResolver resolves, object with ID \'%s\'', 'pop-component-model'),
                $resultItemID
            )
        );
    }

    protected function getUnresolvedResultItemError($resultItem)
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return new Error(
            'unresolved-resultitem',
            sprintf(
                $translationAPI->__('No TypeResolver resolves object \'%s\'', 'pop-component-model'),
                json_encode($resultItem)
            )
        );
    }

    public function resolveValue($resultItem, string $field, ?array $variables = null, ?array $expressions = null, array $options = [])
    {
        // Check that a typeResolver from this Union can process this resultItem, or return an arror
        $typeResolverAndPicker = $this->getTypeResolverAndPicker($resultItem);
        if (is_null($typeResolverAndPicker)) {
            return self::getUnresolvedResultItemError($resultItem);
        }
        // Delegate to the TypeResolver corresponding to this object
        list(
            $typeResolver,
        ) = $typeResolverAndPicker;

        // Delegate to that typeResolver to obtain the value
        return $typeResolver->resolveValue($resultItem, $field, $variables, $expressions, $options);
    }

    protected function addSchemaDefinition(array $stackMessages, array &$generalMessages, array $options = [])
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $typeName = $this->getTypeName();

        // Properties
        if ($description = $this->getSchemaTypeDescription()) {
            $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_DESCRIPTION] = $description;
        }
        $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_IS_UNION] = true;

        // Iterate through the typeResolvers from all the pickers and get their schema definitions
        foreach ($this->getTypeResolverPickers() as $picker) {
            $pickerTypeResolver = $instanceManager->getInstance($picker->getTypeResolverClass());
            $pickerTypeSchemaDefinition = $pickerTypeResolver->getSchemaDefinition($stackMessages, $generalMessages, $options);
            $pickerTypeName = $pickerTypeResolver->getTypeName();
            $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_UNION_TYPES][$pickerTypeName] = $pickerTypeSchemaDefinition[$pickerTypeName];
        }
    }

    protected function processFlatShapeSchemaDefinition(array $options = [])
    {
        parent::processFlatShapeSchemaDefinition($options);

        $typeName = $this->getTypeName();

        // Replace the UnionTypeResolver's types with their typeNames
        $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_UNION_TYPES] = array_keys(
            $this->schemaDefinition[$typeName][SchemaDefinition::ARGNAME_UNION_TYPES]
        );
    }

    /**
     * Because the UnionTypeResolver doesn't know yet which TypeResolver will be used (that depends on each resultItem), it can't resolve error validation
     *
     * @param string $field
     * @param array $variables
     * @return array
     */
    public function resolveSchemaValidationErrorDescriptions(string $field, array &$variables = null): array
    {
        return [];
    }
}
