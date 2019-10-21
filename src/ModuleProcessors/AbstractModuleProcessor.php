<?php
namespace PoP\ComponentModel\ModuleProcessors;
use PoP\Hooks\Facades\HooksAPIFacade;
use PoP\ComponentModel\Facades\Managers\ModuleFilterManagerFacade;
use PoP\ComponentModel\Managers\ModuleFilterManager;
use PoP\ComponentModel\Facades\Managers\ModulePathHelpersFacade;
use PoP\ComponentModel\Modules\ModuleUtils;
use PoP\ComponentModel\ModuleFilters\ModulePaths;
use PoP\ComponentModel\Facades\Managers\InstanceManagerFacade;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;
use PoP\ComponentModel\Facades\Managers\ModuleProcessorManagerFacade;
use PoP\ComponentModel\Engine_Vars;
use PoP\ComponentModel\DataloadUtils;
use PoP\ComponentModel\Utils;
use PoP\ComponentModel\GeneralUtils;
use PoP\ComponentModel\QueryInputOutputHandlers\ParamConstants;
use PoP\ComponentModel\Settings\SettingsManagerFactory;

abstract class AbstractModuleProcessor implements ModuleProcessorInterface
{
    use ModulePathProcessorTrait;

    public const HOOK_INIT_MODEL_PROPS = __CLASS__.':initModelProps';
    public const HOOK_INIT_REQUEST_PROPS = __CLASS__.':initRequestProps';
    public const HOOK_ADD_HEADDATASETMODULE_DATAPROPERTIES = __CLASS__.':addHeaddatasetmoduleDataProperties';

    protected const MODULECOMPONENT_SUBMODULES = 'submodules';
    protected const MODULECOMPONENT_DOMAINSWITCHINGSUBMODULES = 'domain-switching-submodules';
    protected const MODULECOMPONENT_CONDITIONALONDATAFIELDSUBMODULES = 'conditional-on-data-field-submodules';
    protected const MODULECOMPONENT_CONDITIONALONDATAFIELDDOMAINSWITCHINGSUBMODULES = 'conditional-on-data-field-domain-switching-submodules';

    public function getSubmodules(array $module): array
    {
        return array();
    }

    final public function getAllSubmodules(array $module): array
    {
        return $this->getSubmodulesByGroup($module);
    }

    // public function getNature(array $module)
    // {
    //     return null;
    // }

    //-------------------------------------------------
    // New PUBLIC Functions: Atts
    //-------------------------------------------------

    public function executeInitPropsModuletree($eval_self_fn, $get_props_for_descendant_modules_fn, $get_props_for_descendant_datasetmodules_fn, $propagate_fn, array $module, array &$props, $wildcard_props_to_propagate, $targetted_props_to_propagate)
    {
        // Convert the module to its string representation to access it in the array
        $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
        $moduleFullName = ModuleUtils::getModuleFullName($module);

        // Initialize. If this module had been added props, then use them already
        // 1st element to merge: the general props for this module passed down the line
        // 2nd element to merge: the props set exactly to the path. They have more priority, that's why they are 2nd
        // It may contain more than one group (POP_PROPS_ATTRIBUTES). Eg: maybe also POP_PROPS_JSMETHODS
        $props[$moduleFullName] = array_merge_recursive(
            $targetted_props_to_propagate[$moduleFullName] ?? array(),
            $props[$moduleFullName] ?? array()
        );

        // The module must be at the head of the $props array passed to all `initModelProps`, so that function `getPathHeadModule` can work
        $module_props = array(
            $moduleFullName => &$props[$moduleFullName],
        );

        // If ancestor modules set general props, or props targetted at this current module, then add them to the current module props
        foreach ($wildcard_props_to_propagate as $key => $value) {
            $this->setProp($module, $module_props, $key, $value);
        }

        // Before initiating the current level, set the children attributes on the array, so that doing ->setProp, ->appendProp, etc, keeps working
        $module_props[$moduleFullName][POP_PROPS_DESCENDANTATTRIBUTES] = array_merge(
            $module_props[$moduleFullName][POP_PROPS_DESCENDANTATTRIBUTES] ?? array(),
            $targetted_props_to_propagate ?? array()
        );

        // Initiate the current level.
        $this->$eval_self_fn($module, $module_props);

        // Immediately after initiating the current level, extract all child attributes out from the $props, and place it on the other variable
        $targetted_props_to_propagate = $module_props[$moduleFullName][POP_PROPS_DESCENDANTATTRIBUTES];
        unset($module_props[$moduleFullName][POP_PROPS_DESCENDANTATTRIBUTES]);

        // But because modules can't repeat themselves down the line (or it would generate an infinite loop), then can remove the current module from the targeted props
        unset($targetted_props_to_propagate[$moduleFullName]);

        // Allow the $module to add general props for all its descendant modules
        $wildcard_props_to_propagate = array_merge(
            $wildcard_props_to_propagate,
            $this->$get_props_for_descendant_modules_fn($module, $module_props)
        );

        // Propagate
        $modulefilter_manager = ModuleFilterManagerFacade::getInstance();
        $submodules = $this->getAllSubmodules($module);
        $submodules = $modulefilter_manager->removeExcludedSubmodules($module, $submodules);

        // This function must be called always, to register matching modules into requestmeta.filtermodules even when the module has no submodules
        $modulefilter_manager->prepareForPropagation($module, $props);
        if ($submodules) {
            $props[$moduleFullName][POP_PROPS_SUBMODULES] = $props[$moduleFullName][POP_PROPS_SUBMODULES] ?? array();
            foreach ($submodules as $submodule) {
                $submodule_processor = $moduleprocessor_manager->getProcessor($submodule);
                $submodule_wildcard_props_to_propagate = $wildcard_props_to_propagate;

                // If the submodule belongs to the same dataset (meaning that it doesn't have a dataloader of its own), then set the shared attributies for the same-dataset modules
                if (!$submodule_processor->getDataloaderClass($submodule)) {
                    $submodule_wildcard_props_to_propagate = array_merge(
                        $submodule_wildcard_props_to_propagate,
                        $this->$get_props_for_descendant_datasetmodules_fn($module, $module_props)
                    );
                }

                $submodule_processor->$propagate_fn($submodule, $props[$moduleFullName][POP_PROPS_SUBMODULES], $submodule_wildcard_props_to_propagate, $targetted_props_to_propagate);
            }
        }
        $modulefilter_manager->restoreFromPropagation($module, $props);
    }

    public function initModelPropsModuletree(array $module, array &$props, $wildcard_props_to_propagate, $targetted_props_to_propagate)
    {
        $this->executeInitPropsModuletree('initModelProps', 'getModelPropsForDescendantModules', 'getModelPropsForDescendantDatasetmodules', __FUNCTION__, $module, $props, $wildcard_props_to_propagate, $targetted_props_to_propagate);
    }

    public function getModelPropsForDescendantModules(array $module, array &$props): array
    {
        $ret = array();

        // If we set property 'skip-data-load' on any module, not just dataset, spread it down to its children so it reaches its contained dataset submodules
        $skip_data_load = $this->getProp($module, $props, 'skip-data-load');
        if (!is_null($skip_data_load)) {
            $ret['skip-data-load'] = $skip_data_load;
        }

        // Property 'ignore-request-params' => true makes a dataloader module not get values from $_REQUEST
        $ignore_params_from_request = $this->getProp($module, $props, 'ignore-request-params');
        if (!is_null($ignore_params_from_request)) {
            $ret['ignore-request-params'] = $ignore_params_from_request;
        }

        return $ret;
    }

    public function getModelPropsForDescendantDatasetmodules(array $module, array &$props): array
    {
        $ret = array();

        // If this module loads data, then add several properties
        if ($this->getDataloaderClass($module)) {
            if ($this->queriesExternalDomain($module, $props)) {
                $ret['external-domain'] = true;
            }

            // If it is multidomain, add a flag for inner layouts to know and react
            if ($this->isMultidomain($module, $props)) {
                $ret['multidomain'] = true;
            }
        }

        return $ret;
    }

    public function initModelProps(array $module, array &$props)
    {
        // If it is a dataloader module, then set all the props related to data
        if ($dataloader_class = $this->getDataloaderClass($module)) {
            // If it is multidomain, add a flag for inner layouts to know and react
            if ($this->isMultidomain($module, $props)) {
                // $this->add_general_prop($props, 'is-multidomain', true);
                $this->appendProp($module, $props, 'class', 'pop-multidomain');
            }
        }

        // Set property "succeeding-dataloader" on every module, so they know which is their dataloader, needed to calculate the subcomponent data-fields when using dataloader "*"
        if ($dataloader_class) {
            $this->setProp($module, $props, 'succeeding-dataloader', $dataloader_class);
        }
        // Get the prop assigned to the module by its ancestor
        else {
            $dataloader_class = $this->getProp($module, $props, 'succeeding-dataloader');
        }
        if ($dataloader_class) {
            // Set the property "succeeding-dataloader" on all descendants: the same dataloader for all submodules, and the explicit one (or get the default one for "*") for relational objects
            foreach ($this->getSubmodules($module) as $submodule) {
                $this->setProp($submodule, $props, 'succeeding-dataloader', $dataloader_class);
            }
            foreach ($this->getDomainSwitchingSubmodules($module) as $subcomponent_data_field => $subcomponent_dataloader_options) {
                foreach ($subcomponent_dataloader_options as $subcomponent_dataloader_class => $subcomponent_modules) {
                    // If the subcomponent dataloader is not explicitly set in `getDomainSwitchingSubmodules`, then retrieve it now from the current dataloader's fieldResolver
                    if ($subcomponent_dataloader_class == POP_CONSTANT_SUBCOMPONENTDATALOADER_DEFAULTFROMFIELD) {
                        $subcomponent_dataloader_class = DataloadUtils::getDefaultDataloaderNameFromSubcomponentDataField($dataloader_class, $subcomponent_data_field);
                    }
                    // If passing a subcomponent fieldname that doesn't exist to the API, then $subcomponent_dataloader_class will be empty
                    if ($subcomponent_dataloader_class) {
                        foreach ($subcomponent_modules as $subcomponent_module) {
                            $this->setProp($subcomponent_module, $props, 'succeeding-dataloader', $subcomponent_dataloader_class);
                        }
                    }
                }
            }
            foreach ($this->getConditionalOnDataFieldSubmodules($module) as $conditionDataField => $conditionalSubmodules) {
                foreach ($conditionalSubmodules as $conditionalSubmodule) {
                    $this->setProp($conditionalSubmodule, $props, 'succeeding-dataloader', $dataloader_class);
                }
            }
            foreach ($this->getConditionalOnDataFieldDomainSwitchingSubmodules($module) as $conditionDataField => $dataFieldDataloaderOptionsConditionalSubmodules) {
                foreach ($dataFieldDataloaderOptionsConditionalSubmodules as $conditionalDataField => $dataloaderOptionsConditionalSubmodules) {
                    foreach ($dataloaderOptionsConditionalSubmodules as $subcomponentDataloaderClass => $conditionalSubmodules) {
                        // If the subcomponent dataloader is not explicitly set in `getConditionalOnDataFieldDomainSwitchingSubmodules`, then retrieve it now from the current dataloader's fieldResolver
                        if ($subcomponentDataloaderClass == POP_CONSTANT_SUBCOMPONENTDATALOADER_DEFAULTFROMFIELD) {
                            $subcomponentDataloaderClass = DataloadUtils::getDefaultDataloaderNameFromSubcomponentDataField($dataloader_class, $conditionalDataField);
                        }
                        // If passing a subcomponent fieldname that doesn't exist to the API, then $subcomponentDataloaderClass will be empty
                        if ($subcomponentDataloaderClass) {
                            foreach ($conditionalSubmodules as $conditionalSubmodule) {
                                $this->setProp($conditionalSubmodule, $props, 'succeeding-dataloader', $subcomponentDataloaderClass);
                            }
                        }
                    }
                }
            }
        }

        /**
         * Allow to add more stuff
         */
        HooksAPIFacade::getInstance()->doAction(
            self::HOOK_INIT_MODEL_PROPS,
            array(&$props),
            $module,
            $this
        );
    }

    public function initRequestPropsModuletree(array $module, array &$props, $wildcard_props_to_propagate, $targetted_props_to_propagate)
    {
        $this->executeInitPropsModuletree('initRequestProps', 'getRequestPropsForDescendantModules', 'getRequestPropsForDescendantDatasetmodules', __FUNCTION__, $module, $props, $wildcard_props_to_propagate, $targetted_props_to_propagate);
    }

    public function getRequestPropsForDescendantModules(array $module, array &$props): array
    {
        return array();
    }

    public function getRequestPropsForDescendantDatasetmodules(array $module, array &$props): array
    {
        return array();
    }

    public function initRequestProps(array $module, array &$props)
    {
        /**
         * Allow to add more stuff
         */
        HooksAPIFacade::getInstance()->doAction(
            self::HOOK_INIT_REQUEST_PROPS,
            array(&$props),
            $module,
            $this
        );
    }

    //-------------------------------------------------
    // PRIVATE Functions: Atts
    //-------------------------------------------------

    private function getPathHeadModule(array &$props): string
    {
        // From the root of the $props we obtain the current module
        reset($props);
        return key($props);
    }

    private function isModulePath(array $module_or_modulepath): bool
    {
        // $module_or_modulepath can be either a single module (the current one, or its descendant), or a targetted path of modules
        // Because a module is itself represented as an array, to know which is the case, we must ask if it is:
        // - an array => single module
        // - an array of arrays (module path)
        return is_array($module_or_modulepath[0]);
    }

    private function isDescendantModule(array $module_or_modulepath, array &$props): bool
    {
        // If it is not an array of arrays, then this array is directly the module, or the descendant module on which to set the property
        if (!$this->isModulePath($module_or_modulepath)) {
            // From the root of the $props we obtain the current module
            $moduleFullName = $this->getPathHeadModule($props);

            // If the module were we are adding the att, is this same module, then we are already at the path
            // If it is not, then go down one level to that module
            return ($moduleFullName !== ModuleUtils::getModuleFullName($module_or_modulepath));
        }

        return false;
    }

    protected function getModulepath(array $module_or_modulepath, array &$props): array
    {
        // This function is used to get the path to the current module, or to a module path
        // It is not used for getting the path to a single module which is not the current one (since we do not know its path)
        if (!$props) {
            return array();
        }

        // From the root of the $props we obtain the current module
        $moduleFullName = $this->getPathHeadModule($props);

        // Calculate the path to iterate down. It always starts with the current module
        $ret = array($moduleFullName);

        // If it is an array, then we're passing the path to find the module to which to add the att
        if ($this->isModulePath($module_or_modulepath)) {
            $ret = array_merge(
                $ret,
                array_map(
                    [ModuleUtils::class, 'getModuleFullName'],
                    $module_or_modulepath
                )
            );
        }

        return $ret;
    }

    protected function addPropGroupField(string $group, array $module_or_modulepath, array &$props, $field, $value, array $starting_from_modulepath = array(), array $options = array()): void
    {
        // Iterate down to the submodule, which must be an array of modules
        if ($starting_from_modulepath) {
            // Convert it to string
            $startingFromModulepathFullNames = array_map(
                [ModuleUtils::class, 'getModuleFullName'],
                $starting_from_modulepath
            );

            // Attach the current module, which is not included on "starting_from", to step down this level too
            $moduleFullName = $this->getPathHeadModule($props);
            array_unshift($startingFromModulepathFullNames, $moduleFullName);

            // Descend into the path to find the module for which to add the att
            $module_props = &$props;
            foreach ($startingFromModulepathFullNames as $pathlevelModuleFullName) {
                $last_module_props = &$module_props;
                $lastModuleFullName = $pathlevelModuleFullName;

                $module_props[$pathlevelModuleFullName][POP_PROPS_SUBMODULES] = $module_props[$pathlevelModuleFullName][POP_PROPS_SUBMODULES] ?? array();
                $module_props = &$module_props[$pathlevelModuleFullName][POP_PROPS_SUBMODULES];
            }

            // This is the new $props, so it starts from here
            // Save the current $props, and restore later, to make sure this array has only one key, otherwise it will not work
            $current_props = $props;
            $props = array(
                $lastModuleFullName => &$last_module_props[$lastModuleFullName]
            );
        }

        // If the module is a string, there are 2 possibilities: either it is the current module or not
        // If it is not, then it is a descendant module, which will appear at some point down the path.
        // For that case, simply save it under some other entry, from where it will propagate the props later on in `initModelPropsModuletree`
        if ($this->isDescendantModule($module_or_modulepath, $props)) {
            // It is a child module
            $att_module = $module_or_modulepath;
            $attModuleFullName = ModuleUtils::getModuleFullName($att_module);

            // From the root of the $props we obtain the current module
            $moduleFullName = $this->getPathHeadModule($props);

            // Set the child attributes under a different entry
            $props[$moduleFullName][POP_PROPS_DESCENDANTATTRIBUTES] = $props[$moduleFullName][POP_PROPS_DESCENDANTATTRIBUTES] ?? array();
            $module_props = &$props[$moduleFullName][POP_PROPS_DESCENDANTATTRIBUTES];
        } else {
            // Calculate the path to iterate down
            $modulepath = $this->getModulepath($module_or_modulepath, $props);

            // Extract the lastlevel, that's the module to with to add the att
            $attModuleFullName = array_pop($modulepath);

            // Descend into the path to find the module for which to add the att
            $module_props = &$props;
            foreach ($modulepath as $pathlevelFullName) {
                $module_props[$pathlevelFullName][POP_PROPS_SUBMODULES] = $module_props[$pathlevelFullName][POP_PROPS_SUBMODULES] ?? array();
                $module_props = &$module_props[$pathlevelFullName][POP_PROPS_SUBMODULES];
            }
        }

        // Now can proceed to add the att
        $module_props[$attModuleFullName][$group] = $module_props[$attModuleFullName][$group] ?? array();

        if ($options['append']) {
            $module_props[$attModuleFullName][$group][$field] = $module_props[$attModuleFullName][$group][$field] ?? '';
            $module_props[$attModuleFullName][$group][$field] .= ' ' . $value;
        } elseif ($options['array']) {
            $module_props[$attModuleFullName][$group][$field] = $module_props[$attModuleFullName][$group][$field] ?? array();
            if ($options['merge']) {
                $module_props[$attModuleFullName][$group][$field] = array_merge(
                    $module_props[$attModuleFullName][$group][$field],
                    $value
                );
            } elseif ($options['merge-iterate-key']) {
                foreach ($value as $value_key => $value_value) {
                    if (!$module_props[$attModuleFullName][$group][$field][$value_key]) {
                        $module_props[$attModuleFullName][$group][$field][$value_key] = array();
                    }
                    // Doing array_unique, because in the NotificationPreviewLayout, different layouts might impose a JS down the road, many times, and these get duplicated
                    $module_props[$attModuleFullName][$group][$field][$value_key] = array_unique(
                        array_merge(
                            $module_props[$attModuleFullName][$group][$field][$value_key],
                            $value_value
                        )
                    );
                }
            } elseif ($options['push']) {
                array_push($module_props[$attModuleFullName][$group][$field], $value);
            }
        } else {
            // If already set, then do nothing
            if (!isset($module_props[$attModuleFullName][$group][$field])) {
                $module_props[$attModuleFullName][$group][$field] = $value;
            }
        }

        // Restore original $props
        if ($starting_from_modulepath) {
            $props = $current_props;
        }
    }
    protected function getPropGroupField(string $group, array $module, array &$props, string $field, array $starting_from_modulepath = array())
    {
        $group = $this->getPropGroup($group, $module, $props, $starting_from_modulepath);
        return $group[$field];
    }
    protected function getPropGroup(string $group, array $module, array &$props, array $starting_from_modulepath = array()): array
    {
        if (!$props) {
            return array();
        }

        $module_props = &$props;
        foreach ($starting_from_modulepath as $pathlevelModule) {
            $pathlevelModuleFullName = ModuleUtils::getModuleFullName($pathlevelModule);
            $module_props = &$module_props[$pathlevelModuleFullName][POP_PROPS_SUBMODULES];
        }

        $moduleFullName = ModuleUtils::getModuleFullName($module);
        return $module_props[$moduleFullName][$group] ?? array();
    }
    protected function addGroupProp(string $group, array $module_or_modulepath, array &$props, string $field, $value, array $starting_from_modulepath = array()): void
    {
        $this->addPropGroupField($group, $module_or_modulepath, $props, $field, $value, $starting_from_modulepath);
    }
    public function setProp(array $module_or_modulepath, array &$props, string $field, $value, array $starting_from_modulepath = array()): void
    {
        $this->addGroupProp(POP_PROPS_ATTRIBUTES, $module_or_modulepath, $props, $field, $value, $starting_from_modulepath);
    }
    public function appendGroupProp(string $group, array $module_or_modulepath, array &$props, string $field, $value, array $starting_from_modulepath = array()): void
    {
        $this->addPropGroupField($group, $module_or_modulepath, $props, $field, $value, $starting_from_modulepath, array('append' => true));
    }
    public function appendProp(array $module_or_modulepath, array &$props, string $field, $value, array $starting_from_modulepath = array()): void
    {
        $this->appendGroupProp(POP_PROPS_ATTRIBUTES, $module_or_modulepath, $props, $field, $value, $starting_from_modulepath);
    }
    public function mergeGroupProp(string $group, array $module_or_modulepath, array &$props, string $field, $value, array $starting_from_modulepath = array()): void
    {
        $this->addPropGroupField($group, $module_or_modulepath, $props, $field, $value, $starting_from_modulepath, array('array' => true, 'merge' => true));
    }
    public function mergeProp(array $module_or_modulepath, array &$props, string $field, $value, array $starting_from_modulepath = array()): void
    {
        $this->mergeGroupProp(POP_PROPS_ATTRIBUTES, $module_or_modulepath, $props, $field, $value, $starting_from_modulepath);
    }
    public function getGroupProp(string $group, array $module, array &$props, string $field, array $starting_from_modulepath = array())
    {
        return $this->getPropGroupField($group, $module, $props, $field, $starting_from_modulepath);
    }
    public function getProp(array $module, array &$props, string $field, array $starting_from_modulepath = array())
    {
        return $this->getGroupProp(POP_PROPS_ATTRIBUTES, $module, $props, $field, $starting_from_modulepath);
    }
    public function mergeGroupIterateKeyProp(string $group, array $module_or_modulepath, array &$props, string $field, $value, array $starting_from_modulepath = array()): void
    {
        $this->addPropGroupField($group, $module_or_modulepath, $props, $field, $value, $starting_from_modulepath, array('array' => true, 'merge-iterate-key' => true));
    }
    public function mergeIterateKeyProp(array $module_or_modulepath, array &$props, string $field, $value, array $starting_from_modulepath = array()): void
    {
        $this->mergeGroupIterateKeyProp(POP_PROPS_ATTRIBUTES, $module_or_modulepath, $props, $field, $value, $starting_from_modulepath);
    }
    public function pushProp(string $group, array $module_or_modulepath, array &$props, string $field, $value, array $starting_from_modulepath = array()): void
    {
        $this->addPropGroupField($group, $module_or_modulepath, $props, $field, $value, $starting_from_modulepath, array('array' => true, 'push' => true));
    }

    //-------------------------------------------------
    // New PUBLIC Functions: Model Static Settings
    //-------------------------------------------------

    public function getDatabaseKeys(array $module, array &$props): array
    {
        $ret = array();

        $instanceManager = InstanceManagerFacade::getInstance();
        if ($dataloader_class = $this->getDataloaderClass($module)) {
            $dataloader = $instanceManager->getInstance($dataloader_class);

            if ($dbkey = $dataloader->getDatabaseKey()) {
                // Place it under "id" because it is for fetching the current object from the DB, which is found through dbObject.id
                $ret['id'] = $dbkey;
            }
        }

        // This prop is set for both dataloading and non-dataloading modules
        if ($dataloader_class = $this->getProp($module, $props, 'succeeding-dataloader')) {
            foreach ($this->getDomainSwitchingSubmodules($module) as $subcomponent_data_field => $subcomponent_dataloader_options) {
                // Watch out that, if a module has 2 subcomponents on the same data-field but different dataloaders, then
                // the dataloaders' db-key must be the same! Otherwise, the 2nd one will override the 1st one
                // Eg: a module using POSTLIST, another one using CONVERTIBLEPOSTLIST, it doesn't conflict since the db-key for both is "posts"
                $subcomponent_dataloader_classes = array_keys($subcomponent_dataloader_options);
                foreach ($subcomponent_dataloader_classes as $subcomponent_dataloader_class) {
                    // If the subcomponent dataloader is not explicitly set in `getDomainSwitchingSubmodules`, then retrieve it now from the current dataloader's fieldResolver
                    if ($subcomponent_dataloader_class == POP_CONSTANT_SUBCOMPONENTDATALOADER_DEFAULTFROMFIELD) {
                        $subcomponent_dataloader_class = DataloadUtils::getDefaultDataloaderNameFromSubcomponentDataField($dataloader_class, $subcomponent_data_field);
                    }

                    // If passing a subcomponent fieldname that doesn't exist to the API, then $subcomponent_dataloader_class will be empty
                    if ($subcomponent_dataloader_class) {
                        $subcomponent_dataloader = $instanceManager->getInstance($subcomponent_dataloader_class);
                        // If there is an alias, store the results under this. Otherwise, on the fieldName+fieldArgs
                        $subcomponent_data_field_outputkey = FieldQueryInterpreterFacade::getInstance()->getFieldOutputKey($subcomponent_data_field);
                        $ret[$subcomponent_data_field_outputkey] = $subcomponent_dataloader->getDatabaseKey();
                    }
                }
            }
            foreach ($this->getConditionalOnDataFieldDomainSwitchingSubmodules($module) as $conditionDataField => $dataFieldDataloaderOptionsConditionalSubmodules) {
                foreach ($dataFieldDataloaderOptionsConditionalSubmodules as $conditionalDataField => $dataloaderOptionsConditionalSubmodules) {
                    $subcomponentDataloaderClasses = array_keys($dataloaderOptionsConditionalSubmodules);
                    foreach ($subcomponentDataloaderClasses as $subcomponentDataloaderClass) {
                        // If the subcomponent dataloader is not explicitly set in `getConditionalOnDataFieldDomainSwitchingSubmodules`, then retrieve it now from the current dataloader's fieldResolver
                        if ($subcomponentDataloaderClass == POP_CONSTANT_SUBCOMPONENTDATALOADER_DEFAULTFROMFIELD) {
                            $subcomponentDataloaderClass = DataloadUtils::getDefaultDataloaderNameFromSubcomponentDataField($dataloader_class, $conditionalDataField);
                        }
                        // If passing a subcomponent fieldname that doesn't exist to the API, then $subcomponentDataloaderClass will be empty
                        if ($subcomponentDataloaderClass) {
                            $subcomponent_dataloader = $instanceManager->getInstance($subcomponentDataloaderClass);
                            // If there is an alias, store the results under this. Otherwise, on the fieldName+fieldArgs
                            $subcomponent_data_field_outputkey = FieldQueryInterpreterFacade::getInstance()->getFieldOutputKey($conditionalDataField);
                            $ret[$subcomponent_data_field_outputkey] = $subcomponent_dataloader->getDatabaseKey();
                        }
                    }
                }
            }
        }

        return $ret;
    }

    //-------------------------------------------------
    // New PUBLIC Functions: Model Static Settings
    //-------------------------------------------------

    public function getImmutableSettingsDatasetmoduletree(array $module, array &$props): array
    {
        $options = array(
            'only-execute-on-dataloading-modules' => true,
        );
        return $this->executeOnSelfAndPropagateToModules('getImmutableDatasetsettings', __FUNCTION__, $module, $props, true, $options);
    }

    public function getImmutableDatasetsettings(array $module, array &$props): array
    {
        $ret = array();

        if ($database_keys = $this->getDatasetDatabaseKeys($module, $props)) {
            $ret['dbkeys'] = $database_keys;
        }

        return $ret;
    }

    protected function hasNoDataloader(array $module): bool
    {
        $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
        return is_null($moduleprocessor_manager->getProcessor($module)->getDataloaderClass($module));
    }

    protected function addToDatasetDatabaseKeys(array $module, array &$props, $path, &$ret)
    {
        // Add the current module's dbkeys
        $dbkeys = $this->getDatabaseKeys($module, $props);
        foreach ($dbkeys as $field => $dbkey) {
            $field_outputkey = FieldQueryInterpreterFacade::getInstance()->getFieldOutputKey($field);
            $ret[implode('.', array_merge($path, [$field_outputkey]))] = $dbkey;
        }

        // Propagate to all submodules which have no dataloader
        $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
        $moduleFullName = ModuleUtils::getModuleFullName($module);

        $modulefilter_manager = ModuleFilterManagerFacade::getInstance();
        $modulefilter_manager->prepareForPropagation($module, $props);
        foreach ($this->getDomainSwitchingSubmodules($module) as $subcomponent_data_field => $subcomponent_dataloader_options) {
            $subcomponent_data_field_outputkey = FieldQueryInterpreterFacade::getInstance()->getFieldOutputKey($subcomponent_data_field);
            foreach ($subcomponent_dataloader_options as $subcomponent_dataloader_class => $subcomponent_modules) {
                // Only modules without dataloader
                $subcomponent_modules = array_filter($subcomponent_modules, array($this, 'hasNoDataloader'));
                foreach ($subcomponent_modules as $subcomponent_module) {
                    $moduleprocessor_manager->getProcessor($subcomponent_module)->addToDatasetDatabaseKeys($subcomponent_module, $props[$moduleFullName][POP_PROPS_SUBMODULES], array_merge($path, [$subcomponent_data_field_outputkey]), $ret);
                }
            }
        }
        foreach ($this->getConditionalOnDataFieldDomainSwitchingSubmodules($module) as $conditionDataField => $dataFieldDataloaderOptionsConditionalSubmodules) {
            foreach ($dataFieldDataloaderOptionsConditionalSubmodules as $conditionalDataField => $dataloaderOptionsConditionalSubmodules) {
                $subcomponent_data_field_outputkey = FieldQueryInterpreterFacade::getInstance()->getFieldOutputKey($conditionalDataField);
                foreach ($dataloaderOptionsConditionalSubmodules as $subcomponent_dataloader_class => $subcomponent_modules) {
                    // Only modules without dataloader
                    $subcomponent_modules = array_filter($subcomponent_modules, array($this, 'hasNoDataloader'));
                    foreach ($subcomponent_modules as $subcomponent_module) {
                        $moduleprocessor_manager->getProcessor($subcomponent_module)->addToDatasetDatabaseKeys($subcomponent_module, $props[$moduleFullName][POP_PROPS_SUBMODULES], array_merge($path, [$subcomponent_data_field_outputkey]), $ret);
                    }
                }
            }
        }

        // Only modules without dataloader
        $submodules = array_filter($this->getSubmodules($module), array($this, 'hasNoDataloader'));
        foreach ($submodules as $submodule) {
            $moduleprocessor_manager->getProcessor($submodule)->addToDatasetDatabaseKeys($submodule, $props[$moduleFullName][POP_PROPS_SUBMODULES], $path, $ret);
        }
        $modulefilter_manager->restoreFromPropagation($module, $props);
    }

    public function getDatasetDatabaseKeys(array $module, array &$props): array
    {
        $ret = array();
        $this->addToDatasetDatabaseKeys($module, $props, array(), $ret);
        return $ret;
    }

    //-------------------------------------------------
    // New PUBLIC Functions: Static + Stateful Data
    //-------------------------------------------------

    public function getDatasource(array $module, array &$props): string
    {
        // Each module can only return one piece of data, and it must be indicated if it static or mutableonrequest
        // Retrieving only 1 piece is needed so that its children do not get confused what data their getDataFields applies to
        return POP_DATALOAD_DATASOURCE_MUTABLEONREQUEST;
    }

    public function getDBObjectIDOrIDs(array $module, array &$props, &$data_properties)
    {
        return array();
    }

    public function getDataloaderClass(array $module): ?string
    {
        return null;
    }

    public function getActionexecuterClass(array $module): ?string
    {
        return null;
    }

    public function prepareDataPropertiesAfterActionexecution(array $module, array &$props, &$data_properties)
    {
        // Do nothing
    }

    public function getDataFields(array $module, array &$props): array
    {
        return array();
    }

    public function getDomainSwitchingSubmodules(array $module): array
    {
        return array();
    }

    public function getConditionalOnDataFieldSubmodules(array $module): array
    {
        return array();
    }

    public function getConditionalOnDataFieldDomainSwitchingSubmodules(array $module): array
    {
        return array();
    }

    //-------------------------------------------------
    // New PUBLIC Functions: Data Properties
    //-------------------------------------------------

    public function getImmutableDataPropertiesDatasetmoduletree(array $module, array &$props): array
    {
        // The data-properties start on a dataloading module, and finish on the next dataloding module down the line
        // This way, we can collect all the data-fields that the module will need to load for its dbobjects
        return $this->executeOnSelfAndPropagateToModules('getImmutableDataPropertiesDatasetmoduletreeFullsection', __FUNCTION__, $module, $props, false);
    }

    public function getImmutableDataPropertiesDatasetmoduletreeFullsection(array $module, array &$props): array
    {
        $ret = array();

        // Only if this module has a dataloader => We are at the head nodule of the dataset section
        if ($this->getDataloaderClass($module)) {
            // Load the data-fields from all modules inside this section
            // And then, only for the top node, add its extra properties
            $properties = array_merge(
                $this->getDatasetmoduletreeSectionFlattenedDataFields($module, $props),
                $this->getImmutableHeaddatasetmoduleDataProperties($module, $props)
            );

            if ($properties) {
                $ret[POP_CONSTANT_DATAPROPERTIES] = $properties;
            }
        }

        return $ret;
    }

    public function outputConditionFields(array $module, array &$props): bool
    {
        return true;
    }

    public function getDatasetmoduletreeSectionFlattenedDataFields(array $module, array &$props): array
    {
        $ret = array();

        // Check if to add the conditionFields to the output or not
        $outputConditionFields = $this->outputConditionFields($module, $props);

        // Calculate the data-fields from merging them with the subcomponent modules' keys, which are data-fields too
        if ($data_fields = array_unique(
            array_merge(
                $this->getDataFields($module, $props),
                array_keys($this->getDomainSwitchingSubmodules($module)),
                $outputConditionFields ? array_keys($this->getConditionalOnDataFieldSubmodules($module)) : [],
                $outputConditionFields ? array_keys($this->getConditionalOnDataFieldDomainSwitchingSubmodules($module)) : []
            )
        )) {
            $ret['data-fields'] = $data_fields;
        }

        // Propagate down to the components
        $this->flattenDatasetmoduletreeDataProperties(__FUNCTION__, $ret, $module, $props);

        // Propagate down to the subcomponent modules
        $this->flattenRelationalDBObjectDataProperties(__FUNCTION__, $ret, $module, $props);

        return $ret;
    }

    public function getDatasetmoduletreeSectionFlattenedModules(array $module): array
    {
        $ret = [];

        $this->addDatasetmoduletreeSectionFlattenedModules($ret, $module);

        return array_values(
            array_unique(
                $ret,
                SORT_REGULAR
            )
        );
    }

    protected function addDatasetmoduletreeSectionFlattenedModules(&$ret, array $module)
    {
        $ret[] = $module;

        // Propagate down to the components
        // $this->flattenDatasetmoduletreeModules(__FUNCTION__, $ret, $module);
        // Exclude the subcomponent modules here
        if ($submodules = $this->getModulesToPropagateDataProperties($module)) {
            $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
            foreach ($submodules as $submodule) {
                $submodule_processor = $moduleprocessor_manager->getProcessor($submodule);

                // Propagate only if the submodule doesn't have a dataloader. If it does, this is the end of the data line, and the submodule is the beginning of a new datasetmoduletree
                if (!$submodule_processor->getDataloaderClass($submodule)) {
                    $submodule_processor->addDatasetmoduletreeSectionFlattenedModules($ret, $submodule);
                }
            }
        }
    }

    // protected function flattenDatasetmoduletreeModules($propagate_fn, &$ret, array $module)
    // {
    //     // Exclude the subcomponent modules here
    //     if ($submodules = $this->getModulesToPropagateDataProperties($module)) {
    //         $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
    //         foreach ($submodules as $submodule) {
    //             $submodule_processor = $moduleprocessor_manager->getProcessor($submodule);

    //             // Propagate only if the submodule doesn't have a dataloader. If it does, this is the end of the data line, and the submodule is the beginning of a new datasetmoduletree
    //             if (!$submodule_processor->getDataloaderClass($submodule)) {
    //                 if ($submodule_ret = $submodule_processor->$propagate_fn($submodule)) {
    //                     $ret = array_merge(
    //                         $ret,
    //                         $submodule_ret
    //                     );
    //                 }
    //             }
    //         }
    //     }
    // }

    public function getImmutableHeaddatasetmoduleDataProperties(array $module, array &$props): array
    {
        // By default return nothing at the last level
        $ret = array();

        // From the State property we find out if it's Static of Stateful
        $datasource = $this->getDatasource($module, $props);
        $ret[ParamConstants::DATASOURCE] = $datasource;

        // Add the properties below either as static or mutableonrequest
        if ($datasource == POP_DATALOAD_DATASOURCE_IMMUTABLE) {
            $this->addHeaddatasetmoduleDataProperties($ret, $module, $props);
        }

        return $ret;
    }

    public function isLazyload(array $module, array &$props): bool
    {
        return $this->getProp($module, $props, 'lazy-load') ?? false;
    }

    protected function addHeaddatasetmoduleDataProperties(&$ret, array $module, array &$props)
    {
        $vars = Engine_Vars::getVars();

        // Is the component lazy-load?
        $ret[ParamConstants::LAZYLOAD] = $this->isLazyload($module, $props);

        // Loading data from a different site?
        $ret[ParamConstants::EXTERNALLOAD] = $this->queriesExternalDomain($module, $props);

        // Do not load data when doing lazy load, unless passing URL param ?action=loadlazy, which is needed to initialize the lazy components.
        // Do not load data for Search page (initially, before the query was submitted)
        // Do not load data when querying data from another domain, since evidently we don't have that data in this site, then the load must be triggered from the client
        $ret[ParamConstants::SKIPDATALOAD] =
            (!in_array(POP_ACTION_LOADLAZY, $vars['actions'])  && $ret[ParamConstants::LAZYLOAD]) ||
            $ret[ParamConstants::EXTERNALLOAD] ||
            $this->getProp($module, $props, 'skip-data-load');

        // Use Mock DB Object Data for the Skeleton Screen
        $ret[ParamConstants::USEMOCKDBOBJECTDATA] = $this->getProp($module, $props, 'use-mock-dbobject-data') ?? false;

        /**
         * Allow to add more stuff
         */
        HooksAPIFacade::getInstance()->doAction(
            self::HOOK_ADD_HEADDATASETMODULE_DATAPROPERTIES,
            array(&$ret),
            $module,
            array(&$props),
            $this
        );
    }

    public function getMutableonmodelDataPropertiesDatasetmoduletree(array $module, array &$props): array
    {
        return $this->executeOnSelfAndPropagateToModules('getMutableonmodelDataPropertiesDatasetmoduletreeFullsection', __FUNCTION__, $module, $props, false);
    }

    public function getMutableonmodelDataPropertiesDatasetmoduletreeFullsection(array $module, array &$props): array
    {
        $ret = array();

        // Only if this module has a dataloader
        if ($this->getDataloaderClass($module)) {
            $properties = $this->getMutableonmodelHeaddatasetmoduleDataProperties($module, $props);
            if ($properties) {
                $ret[POP_CONSTANT_DATAPROPERTIES] = $properties;
            }
        }

        return $ret;
    }

    public function getMutableonmodelHeaddatasetmoduleDataProperties(array $module, array &$props): array
    {
        $ret = array();

        // Add the properties below either as static or mutableonrequest
        $datasource = $this->getDatasource($module, $props);
        if ($datasource == POP_DATALOAD_DATASOURCE_MUTABLEONMODEL) {
            $this->addHeaddatasetmoduleDataProperties($ret, $module, $props);
        }

        // Fetch params from request?
        $ignore_params_from_request = $this->getProp($module, $props, 'ignore-request-params');
        if (!is_null($ignore_params_from_request)) {
            $ret[ParamConstants::IGNOREREQUESTPARAMS] = $ignore_params_from_request;
        }

        return $ret;
    }

    public function getMutableonrequestDataPropertiesDatasetmoduletree(array $module, array &$props): array
    {
        return $this->executeOnSelfAndPropagateToModules('getMutableonrequestDataPropertiesDatasetmoduletreeFullsection', __FUNCTION__, $module, $props, false);
    }

    public function getMutableonrequestDataPropertiesDatasetmoduletreeFullsection(array $module, array &$props): array
    {
        $ret = array();

        // Only if this module has a dataloader
        if ($this->getDataloaderClass($module)) {
            // // Load the data-fields from all modules inside this section
            // // And then, only for the top node, add its extra properties
            // $properties = array_merge(
            //     $this->get_mutableonrequest_data_properties_datasetmoduletree_section($module, $props),
            //     $this->getMutableonrequestHeaddatasetmoduleDataProperties($module, $props)
            // );
            $properties = $this->getMutableonrequestHeaddatasetmoduleDataProperties($module, $props);

            if ($properties) {
                $ret[POP_CONSTANT_DATAPROPERTIES] = $properties;
            }
        }

        return $ret;
    }

    public function getMutableonrequestHeaddatasetmoduleDataProperties(array $module, array &$props): array
    {
        $ret = array();

        // Add the properties below either as static or mutableonrequest
        $datasource = $this->getDatasource($module, $props);
        if ($datasource == POP_DATALOAD_DATASOURCE_MUTABLEONREQUEST) {
            $this->addHeaddatasetmoduleDataProperties($ret, $module, $props);
        }

        if ($dataload_source = $this->getDataloadSource($module, $props)) {
            $ret[ParamConstants::SOURCE] = $dataload_source;
        }

        // When loading data or execution an action, check if to validate checkpoints?
        // This is in MUTABLEONREQUEST instead of STATIC because the checkpoints can change depending on doingPost()
        // (such as done to set-up checkpoint configuration for POP_USERSTANCE_ROUTE_ADDOREDITSTANCE, or within POPUSERLOGIN_CHECKPOINTCONFIGURATION_REQUIREUSERSTATEONDOINGPOST)
        // if ($checkpoint_configuration = $this->getDataaccessCheckpointConfiguration($module, $props)) {
        if ($checkpoints = $this->getDataaccessCheckpoints($module, $props)) {
            // if (Utils::checkpointValidationRequired($checkpoint_configuration)) {

            // Pass info for PoP Engine
            // $ret[GD_DATALOAD_DATAACCESSCHECKPOINTS] = $checkpoint_configuration['checkpoints'];
            $ret[GD_DATALOAD_DATAACCESSCHECKPOINTS] = $checkpoints;
            // }
        }

        // To trigger the actionexecuter, its own checkpoints must be successful
        // if ($checkpoint_configuration = $this->getActionexecutionCheckpointConfiguration($module, $props)) {
        if ($checkpoints = $this->getActionexecutionCheckpoints($module, $props)) {
            // if (Utils::checkpointValidationRequired($checkpoint_configuration)) {

            // Pass info for PoP Engine
            // $ret[GD_DATALOAD_ACTIONEXECUTIONCHECKPOINTS] = $checkpoint_configuration['checkpoints'];
            $ret[GD_DATALOAD_ACTIONEXECUTIONCHECKPOINTS] = $checkpoints;
            // }
        }

        return $ret;
    }

    //-------------------------------------------------
    // New PUBLIC Functions: Data Feedback
    //-------------------------------------------------

    public function getDataFeedbackDatasetmoduletree(array $module, array &$props, array $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbobjectids): array
    {
        return $this->executeOnSelfAndPropagateToDatasetmodules('getDataFeedbackModuletree', __FUNCTION__, $module, $props, $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbobjectids);
    }

    public function getDataFeedbackModuletree(array $module, array &$props, array $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbobjectids): array
    {
        $ret = array();

        if ($feedback = $this->getDataFeedback($module, $props, $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbobjectids)) {
            $ret[POP_CONSTANT_FEEDBACK] = $feedback;
        }

        return $ret;
    }

    public function getDataFeedback(array $module, array &$props, array $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbobjectids): array
    {
        return array();
    }

    public function getDataFeedbackInterreferencedModulepath(array $module, array &$props): ?array
    {
        return null;
    }

    //-------------------------------------------------
    // Background URLs
    //-------------------------------------------------

    public function getBackgroundurlsMergeddatasetmoduletree(array $module, array &$props, array $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDs): array
    {
        return $this->executeOnSelfAndMergeWithDatasetmodules('getBackgroundurls', __FUNCTION__, $module, $props, $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDs);
    }

    public function getBackgroundurls(array $module, array &$props, array $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDs): array
    {
        return array();
    }

    //-------------------------------------------------
    // Dataset Meta
    //-------------------------------------------------

    public function getDatasetmeta(array $module, array &$props, array $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDOrIDs): array
    {
        $ret = array();

        if ($query_multidomain_urls = $this->getDataloadMultidomainQuerySources($module, $props)) {
            $ret['multidomaindataloadsources'] = $query_multidomain_urls;
        } elseif ($dataload_source = $data_properties[ParamConstants::SOURCE]) {
            $ret['dataloadsource'] = $dataload_source;
        }

        if ($data_properties[ParamConstants::LAZYLOAD]) {
            $ret['lazyload'] = true;
        }
        // if ($data_properties[ParamConstants::EXTERNALLOAD]) {
        //     $ret['externalload'] = true;
        // }

        return $ret;
    }

    //-------------------------------------------------
    // Others
    //-------------------------------------------------

    public function getRelevantRoute(array $module, array &$props): ?string
    {
        return null;
    }

    public function getRelevantRouteCheckpointTarget(array $module, array &$props): string
    {
        return GD_DATALOAD_DATAACCESSCHECKPOINTS;
    }

    protected function maybeOverrideCheckpoints($checkpoints)
    {

        // Allow URE to add the extra checkpoint condition of the user having the Profile role
        return HooksAPIFacade::getInstance()->applyFilters(
            'ModuleProcessor:checkpoints',
            $checkpoints
        );
    }

    // function getDataaccessCheckpointConfiguration(array $module, array &$props) {
    public function getDataaccessCheckpoints(array $module, array &$props): array
    {
        if ($route = $this->getRelevantRoute($module, $props)) {
            if ($this->getRelevantRouteCheckpointTarget($module, $props) == GD_DATALOAD_DATAACCESSCHECKPOINTS) {
                return $this->maybeOverrideCheckpoints(SettingsManagerFactory::getInstance()->getCheckpoints($route));
            }
        }

        // return null;
        return array();
    }

    // function getActionexecutionCheckpointConfiguration(array $module, array &$props) {
    public function getActionexecutionCheckpoints(array $module, array &$props): array
    {
        if ($route = $this->getRelevantRoute($module, $props)) {
            if ($this->getRelevantRouteCheckpointTarget($module, $props) == GD_DATALOAD_ACTIONEXECUTIONCHECKPOINTS) {
                return $this->maybeOverrideCheckpoints(Settings\SettingsManagerFactory::getInstance()->getCheckpoints($route));
            }
        }

        // return null;
        return array();
    }

    public function executeAction(array $module, array &$props)
    {
        // By default, execute only if the module is targeted for execution and doing POST
        $vars = Engine_Vars::getVars();
        return doingPost() && $vars['actionpath'] == ModulePathHelpersFacade::getInstance()->getStringifiedModulePropagationCurrentPath($module);
    }

    public function getDataloadSource(array $module, array &$props): string
    {
        // Because a component can interact with itself by adding ?modulepaths=...,
        // then, by default, we simply set the dataload source to point to itself!
        $stringified_module_propagation_current_path = ModulePathHelpersFacade::getInstance()->getStringifiedModulePropagationCurrentPath($module);
        $ret = GeneralUtils::addQueryArgs([
            ModuleFilterManager::URLPARAM_MODULEFILTER => \PoP\ComponentModel\ModuleFilters\ModulePaths::NAME,
            ModulePaths::URLPARAM_MODULEPATHS.'[]' => $stringified_module_propagation_current_path,
        ], Utils::getCurrentUrl());

        // If we are in the API currently, stay in the API
        $vars = Engine_Vars::getVars();
        if ($vars['scheme'] == POP_SCHEME_API) {
            $ret = \PoP\Engine\APIUtils::getEndpoint($ret, $vars['dataoutputitems']);
        }

        // Allow to add extra modulepaths set from above
        if ($extra_module_paths = $this->getProp($module, $props, 'dataload-source-add-modulepaths')) {
            foreach ($extra_module_paths as $modulepath) {
                $ret = GeneralUtils::addQueryArgs([
                    ModulePaths::URLPARAM_MODULEPATHS.'[]' => ModulePathHelpersFacade::getInstance()->stringifyModulePath($modulepath),
                ], $ret);
            }
        }

        // Add the actionpath too
        if ($this->getActionexecuterClass($module)) {
            $ret = GeneralUtils::addQueryArgs([
                GD_URLPARAM_ACTIONPATH => $stringified_module_propagation_current_path,
            ], $ret);
        }

        // Add the format to the query url
        if ($this instanceof FormattableModuleInterface) {
            if ($format = $this->getFormat($module)) {
                $ret = GeneralUtils::addQueryArgs([
                    GD_URLPARAM_FORMAT => $format,
                ], $ret);
            }
        }

        return $ret;
    }

    public function getDataloadMultidomainSources(array $module, array &$props): array
    {
        if ($sources = $this->getProp($module, $props, 'dataload-multidomain-sources')) {
            return is_array($sources) ? $sources : [$sources];
        }

        return [];
    }

    public function getDataloadMultidomainQuerySources(array $module, array &$props): array
    {
        return $this->getDataloadMultidomainSources($module, $props);
    }

    public function queriesExternalDomain(array $module, array &$props): bool
    {
        if ($sources = $this->getDataloadMultidomainSources($module, $props)) {
            $cmsengineapi = \PoP\Engine\FunctionAPIFactory::getInstance();
            $domain = $cmsengineapi->getSiteURL();
            foreach ($sources as $source) {
                if (substr($source, 0, strlen($domain)) != $domain) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isMultidomain(array $module, array &$props): bool
    {
        if (!$this->queriesExternalDomain($module, $props)) {
            return false;
        }

        $multidomain_urls = $this->getDataloadMultidomainSources($module, $props);
        return is_array($multidomain_urls) && count($multidomain_urls) >= 2;
    }

    public function getModulesToPropagateDataProperties(array $module): array
    {
        return $this->getSubmodulesByGroup(
            $module,
            array(
                self::MODULECOMPONENT_SUBMODULES,
                self::MODULECOMPONENT_CONDITIONALONDATAFIELDSUBMODULES,
            )
        );
    }

    protected function flattenDatasetmoduletreeDataProperties($propagate_fn, &$ret, array $module, array &$props)
    {
        $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
        $moduleFullName = ModuleUtils::getModuleFullName($module);

        // Exclude the subcomponent modules here
        $modulefilter_manager = ModuleFilterManagerFacade::getInstance();
        $modulefilter_manager->prepareForPropagation($module, $props);
        if ($submodules = $this->getModulesToPropagateDataProperties($module)) {

            // Calculate in 2 steps:
            // First step: The conditional-on-data-field-submodules must have their data-fields added under entry "conditional-data-fields"
            if ($conditionalOnDataFieldSubmodules = $this->getConditionalOnDataFieldSubmodules($module)) {
                $directSubmodules = $this->getSubmodules($module);
                // Instead of assigning to $ret, first assign it to a temporary variable, so we can then replace 'data-fields' with 'conditional-data-fields' before merging to $ret
                foreach ($conditionalOnDataFieldSubmodules as $conditionDataField => $conditionalSubmodules) {
                    // Calculate those fields which are certainly to be propagated, and not part of the direct submodules
                    // Using this really ugly way because, for comparing modules, using `array_diff` and `intersect` fail
                    for ($i=count($conditionalSubmodules)-1; $i>=0; $i--) {
                        // If this submodule is also in the direct ones, then it's not conditional anymore
                        if (in_array($conditionalSubmodules[$i], $directSubmodules)) {
                            array_splice($conditionalSubmodules, $i, 1);
                        }
                    }
                    foreach ($conditionalSubmodules as $submodule) {
                        $submodule_processor = $moduleprocessor_manager->getProcessor($submodule);

                        // Propagate only if the submodule doesn't have a dataloader. If it does, this is the end of the data line, and the submodule is the beginning of a new datasetmoduletree
                        if (!$submodule_processor->getDataloaderClass($submodule, $props[$moduleFullName][POP_PROPS_SUBMODULES])) {
                            if ($submodule_ret = $submodule_processor->$propagate_fn($submodule, $props[$moduleFullName][POP_PROPS_SUBMODULES])) {

                                // Chain the "data-fields" from the sublevels under the current "conditional-data-fields"
                                // Move from "data-fields" to "conditional-data-fields"
                                if ($submodule_ret['data-fields']) {
                                    foreach ($submodule_ret['data-fields'] as $submodule_data_field) {
                                        $ret['conditional-data-fields'][$conditionDataField][$submodule_data_field] = [];
                                    }
                                    unset($submodule_ret['data-fields']);
                                }
                                // Chain the conditional-data-fields at the end of the one from this module
                                if ($submodule_ret['conditional-data-fields']) {
                                    foreach ($submodule_ret['conditional-data-fields'] as $submodule_condition_data_field => $submodule_conditional_data_fields) {
                                        $ret['conditional-data-fields'][$conditionDataField][$submodule_condition_data_field] = array_merge(
                                            $ret['conditional-data-fields'][$conditionDataField][$submodule_condition_data_field] ?? [],
                                            $submodule_conditional_data_fields
                                        );
                                    }
                                    unset($submodule_ret['conditional-data-fields']);
                                }

                                // array_merge_recursive => data-fields from different sidebar-components can be integrated all together
                                $ret = array_merge_recursive(
                                    $ret,
                                    $submodule_ret
                                );
                            }
                        }
                    }

                    // Extract the conditional submodules from the rest of the submodules, which will be processed below
                    foreach ($conditionalSubmodules as $conditionalSubmodule) {
                        $pos = array_search($conditionalSubmodule, $submodules);
                        if ($pos !== false) {
                            array_splice($submodules, $pos, 1);
                        }
                    }
                }
            }

            // Second step: all the other submodules can be calculated directly
            foreach ($submodules as $submodule) {
                $submodule_processor = $moduleprocessor_manager->getProcessor($submodule);

                // Propagate only if the submodule doesn't have a dataloader. If it does, this is the end of the data line, and the submodule is the beginning of a new datasetmoduletree
                if (!$submodule_processor->getDataloaderClass($submodule, $props[$moduleFullName][POP_PROPS_SUBMODULES])) {
                    if ($submodule_ret = $submodule_processor->$propagate_fn($submodule, $props[$moduleFullName][POP_PROPS_SUBMODULES])) {
                        // array_merge_recursive => data-fields from different sidebar-components can be integrated all together
                        $ret = array_merge_recursive(
                            $ret,
                            $submodule_ret
                        );
                    }
                }
            }

            // Array Merge appends values when under numeric keys, so we gotta filter duplicates out
            if ($ret['data-fields']) {
                $ret['data-fields'] = array_values(array_unique($ret['data-fields']));
            }
        }
        $modulefilter_manager->restoreFromPropagation($module, $props);
    }

    protected function flattenRelationalDBObjectDataProperties($propagate_fn, &$ret, array $module, array &$props)
    {
        $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
        $moduleFullName = ModuleUtils::getModuleFullName($module);

        // Combine the direct and conditionalOnDataField modules all together to iterate below
        $domainSwitchingSubmodules = $this->getDomainSwitchingSubmodules($module);
        foreach ($this->getConditionalOnDataFieldDomainSwitchingSubmodules($module) as $conditionDataField => $dataFieldDataloaderOptionsConditionalSubmodules) {
            foreach ($dataFieldDataloaderOptionsConditionalSubmodules as $conditionalDataField => $dataloaderOptionsConditionalSubmodules) {
                foreach ($dataloaderOptionsConditionalSubmodules as $subcomponentDataloaderClass => $conditionalSubmodules) {
                    $domainSwitchingSubmodules[$conditionalDataField][$subcomponentDataloaderClass] = array_values(array_unique(array_merge(
                        $conditionalDataField[$conditionalDataField][$subcomponentDataloaderClass] ?? [],
                        $conditionalSubmodules
                    )));
                }
            }
        }

        // If it has subcomponent modules, integrate them under 'subcomponents'
        $modulefilter_manager = ModuleFilterManagerFacade::getInstance();
        $modulefilter_manager->prepareForPropagation($module, $props);
        foreach ($domainSwitchingSubmodules as $subcomponent_data_field => $subcomponent_dataloader_options) {
            foreach ($subcomponent_dataloader_options as $subcomponent_dataloader_class => $subcomponent_modules) {
                $subcomponent_modules_data_properties = array(
                    'data-fields' => array(),
                    'conditional-data-fields' => array(),
                    'subcomponents' => array()
                );
                foreach ($subcomponent_modules as $subcomponent_module) {
                    $subcomponent_processor = $moduleprocessor_manager->getProcessor($subcomponent_module);
                    if ($subcomponent_module_data_properties = $subcomponent_processor->$propagate_fn($subcomponent_module, $props[$moduleFullName][POP_PROPS_SUBMODULES])) {
                        $subcomponent_modules_data_properties = array_merge_recursive(
                            $subcomponent_modules_data_properties,
                            $subcomponent_module_data_properties
                        );
                    }
                }

                $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class] = $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class] ?? array();
                if ($subcomponent_modules_data_properties['data-fields']) {
                    $subcomponent_modules_data_properties['data-fields'] = array_unique($subcomponent_modules_data_properties['data-fields']);
                    $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class]['data-fields'] = array_values(array_unique(array_merge(
                            $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class]['data-fields'] ?? [],
                            $subcomponent_modules_data_properties['data-fields']
                    )));
                }
                if ($subcomponent_modules_data_properties['conditional-data-fields']) {
                    $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class]['conditional-data-fields'] = $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class]['conditional-data-fields'] ?? [];
                    foreach ($subcomponent_modules_data_properties['conditional-data-fields'] as $conditionDataField => $conditionalDataFields) {
                        $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class]['conditional-data-fields'][$conditionDataField] = array_merge_recursive(
                            $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class]['conditional-data-fields'][$conditionDataField] ?? [],
                            $conditionalDataFields
                        );
                    }
                }

                if ($subcomponent_modules_data_properties['subcomponents']) {
                    $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class]['subcomponents'] = $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class]['subcomponents'] ?? array();
                    $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class]['subcomponents'] = array_merge_recursive(
                        $ret['subcomponents'][$subcomponent_data_field][$subcomponent_dataloader_class]['subcomponents'],
                        $subcomponent_modules_data_properties['subcomponents']
                    );
                }
            }
        }
        $modulefilter_manager->restoreFromPropagation($module, $props);
    }


    //-------------------------------------------------
    // New PUBLIC Functions: Static Data
    //-------------------------------------------------

    public function getModelSupplementaryDbobjectdataModuletree(array $module, array &$props): array
    {
        return $this->executeOnSelfAndMergeWithModules('getModelSupplementaryDbobjectdata', __FUNCTION__, $module, $props);
    }

    public function getModelSupplementaryDbobjectdata(array $module, array &$props): array
    {
        return array();
    }

    //-------------------------------------------------
    // New PUBLIC Functions: Stateful Data
    //-------------------------------------------------

    public function getMutableonrequestSupplementaryDbobjectdataModuletree(array $module, array &$props): array
    {
        return $this->executeOnSelfAndMergeWithModules('getMutableonrequestSupplementaryDbobjectdata', __FUNCTION__, $module, $props);
    }

    public function getMutableonrequestSupplementaryDbobjectdata(array $module, array &$props): array
    {
        return array();
    }

    final private function getSubmodulesByGroup(array $module, $components = array()): array
    {
        if (empty($components)) {
            $components = array(
                self::MODULECOMPONENT_SUBMODULES,
                self::MODULECOMPONENT_DOMAINSWITCHINGSUBMODULES,
                self::MODULECOMPONENT_CONDITIONALONDATAFIELDSUBMODULES,
                self::MODULECOMPONENT_CONDITIONALONDATAFIELDDOMAINSWITCHINGSUBMODULES,
            );
        }

        $ret = array();

        if (in_array(self::MODULECOMPONENT_SUBMODULES, $components)) {
            // Modules are arrays, comparing them through the default SORT_STRING fails
            $ret = array_unique(
                $this->getSubmodules($module),
                SORT_REGULAR
            );
        }

        if (in_array(self::MODULECOMPONENT_DOMAINSWITCHINGSUBMODULES, $components)) {
            foreach ($this->getDomainSwitchingSubmodules($module) as $subcomponent_data_field => $subcomponent_dataloader_options) {
                foreach ($subcomponent_dataloader_options as $subcomponent_dataloader_class => $subcomponent_modules) {
                    $ret = array_values(
                        array_unique(
                            array_merge(
                                $subcomponent_modules,
                                $ret
                            ),
                            SORT_REGULAR
                        )
                    );
                }
            }
        }

        if (in_array(self::MODULECOMPONENT_CONDITIONALONDATAFIELDSUBMODULES, $components)) {
            // Modules are arrays, comparing them through the default SORT_STRING fails
            foreach ($this->getConditionalOnDataFieldSubmodules($module) as $data_field => $submodules) {
                $ret = array_unique(
                    array_merge(
                        $ret,
                        $submodules
                    ),
                    SORT_REGULAR
                );
            }
        }

        if (in_array(self::MODULECOMPONENT_CONDITIONALONDATAFIELDDOMAINSWITCHINGSUBMODULES, $components)) {
            foreach ($this->getConditionalOnDataFieldDomainSwitchingSubmodules($module) as $conditionDataField => $dataFieldDataloaderOptionsConditionalSubmodules) {
                foreach ($dataFieldDataloaderOptionsConditionalSubmodules as $conditionalDataField => $dataloaderOptions) {
                    foreach ($dataloaderOptions as $subcomponentDataloaderClass => $subcomponentModules) {
                        $ret = array_values(
                            array_unique(
                                array_merge(
                                    $subcomponentModules,
                                    $ret
                                ),
                                SORT_REGULAR
                            )
                        );
                    }
                }
            }
        }

        return $ret;
    }
}
