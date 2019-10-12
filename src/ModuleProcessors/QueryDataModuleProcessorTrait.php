<?php
namespace PoP\ComponentModel\ModuleProcessors;
use PoP\Hooks\Facades\HooksAPIFacade;
use PoP\ComponentModel\Facades\Managers\InstanceManagerFacade;
use PoP\ComponentModel\Facades\Managers\ModuleProcessorManagerFacade;
use PoP\ComponentModel\QueryHandler\ParamConstants;

trait QueryDataModuleProcessorTrait
{
    protected function getImmutableDataloadQueryArgs(array $module, array &$props): array
    {
        return array();
    }
    protected function getMutableonrequestDataloadQueryArgs(array $module, array &$props): array
    {
        return array();
    }
    public function getQueryHandlerClass(array $module): ?string
    {
        return \PoP\Engine\QueryHandler_ActionExecution::class;
    }
    // public function getFilter(array $module)
    // {
    //     return null;
    // }

    public function getImmutableHeaddatasetmoduleDataProperties(array $module, array &$props): array
    {
        $ret = parent::getImmutableHeaddatasetmoduleDataProperties($module, $props);

        // Attributes to pass to the query
        $ret[ParamConstants::QUERYARGS] = $this->getImmutableDataloadQueryArgs($module, $props);

        return $ret;
    }

    public function getQueryArgsFilteringModules(array $module, array &$props): array
    {
        // Attributes overriding the dataloader args, taken from the request
        return [
            $module,
        ];
    }

    public function getMutableonmodelHeaddatasetmoduleDataProperties(array $module, array &$props): array
    {
        $ret = parent::getMutableonmodelHeaddatasetmoduleDataProperties($module, $props);

        // Attributes overriding the dataloader args, taken from the request
        if (!$ret[ParamConstants::IGNOREREQUESTPARAMS]) {
            $ret[ParamConstants::QUERYARGSFILTERINGMODULES] = $this->getQueryArgsFilteringModules($module, $props);
        }

        // // Set the filter if it has one
        // if ($filter = $this->getFilter($module)) {
        //     $ret[GD_DATALOAD_FILTER] = $filter;
        // }

        return $ret;
    }
    public function filterHeadmoduleDataloadQueryArgs(array $module, array &$query, array $source = null)
    {
        if ($active_filterqueryargs_modules = $this->getActiveDataloadQueryArgsFilteringModules($module, $source)) {
            $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
            global $pop_filterinputprocessor_manager;
            foreach ($active_filterqueryargs_modules as $submodule) {

                $submodule_processor = $moduleprocessor_manager->getProcessor($submodule);
                $value = $submodule_processor->getValue($submodule, $source);
                if ($filterInput = $submodule_processor->getFilterInput($submodule)) {
                    $pop_filterinputprocessor_manager->getProcessor($filterInput)->filterDataloadQueryArgs($filterInput, $query, $value);
                }
            }
        }
    }

    public function getActiveDataloadQueryArgsFilteringModules(array $module, array $source = null): array
    {
        // Search for cached result
        $cacheKey = json_encode($source ?? []);
        $this->activeDataloadQueryArgsFilteringModules[$cacheKey] = $this->activeDataloadQueryArgsFilteringModules[$cacheKey] ?? [];
        if (!is_null($this->activeDataloadQueryArgsFilteringModules[$cacheKey][$module[1]])) {
            return $this->activeDataloadQueryArgsFilteringModules[$cacheKey][$module[1]];
        }

        $modules = [];
        $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
        // Check if the module has any filtercomponent
        if ($filterqueryargs_modules = $this->getDataloadQueryArgsFilteringModules($module)) {
            // Check if if we're currently filtering by any filtercomponent
            $modules = array_filter(
                $filterqueryargs_modules,
                function($module) use($moduleprocessor_manager, $source) {
                    return !is_null($moduleprocessor_manager->getProcessor($module)->getValue($module, $source));
                }
            );
        }

        $this->activeDataloadQueryArgsFilteringModules[$cacheKey][$module[1]] = $modules;
        return $modules;
    }

    public function getDataloadQueryArgsFilteringModules(array $module): array
    {
        $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
        return array_values(array_filter(
            $this->getDatasetmoduletreeSectionFlattenedModules($module),
            function($module) use($moduleprocessor_manager) {
                return $moduleprocessor_manager->getProcessor($module) instanceof \PoP\ComponentModel\DataloadQueryArgsFilter;
            }
        ));
    }

    public function getMutableonrequestHeaddatasetmoduleDataProperties(array $module, array &$props): array
    {
        $ret = parent::getMutableonrequestHeaddatasetmoduleDataProperties($module, $props);

        $ret[ParamConstants::QUERYARGS] = $this->getMutableonrequestDataloadQueryArgs($module, $props);

        return $ret;
    }

    public function getDBObjectIDOrIDs(array $module, array &$props, &$data_properties)
    {
        $instanceManager = InstanceManagerFacade::getInstance();

        // Prepare the Query to get data from the DB
        $datasource = $data_properties[ParamConstants::DATASOURCE];
        if ($datasource == POP_DATALOAD_DATASOURCE_MUTABLEONREQUEST && !$data_properties[ParamConstants::IGNOREREQUESTPARAMS]) {
            // Merge with $_REQUEST, so that params passed through the URL can be used for the query (eg: ?limit=5)
            // But whitelist the params that can be taken, to avoid hackers peering inside the system and getting custom data (eg: params "include", "post-status" => "draft", etc)
            $whitelisted_params = (array)HooksAPIFacade::getInstance()->applyFilters(
                Constants::HOOK_QUERYDATA_WHITELISTEDPARAMS,
                array(
                    GD_URLPARAM_REDIRECTTO,
                    GD_URLPARAM_PAGENUMBER,
                    GD_URLPARAM_LIMIT,
                    // Used for the Comments to know what post to fetch comments from when filtering
                    GD_URLPARAM_COMMENTPOSTID,
                )
            );
            $params_from_request = array_filter(
                $_REQUEST,
                function ($param) use ($whitelisted_params) {
                    return in_array($param, $whitelisted_params);
                },
                ARRAY_FILTER_USE_KEY
            );

            // Handle special cases
            // Avoid users querying all results (by passing limit=-1 or limit=0)
            $cmsengineapi = \PoP\Engine\FunctionAPIFactory::getInstance();
            if (isset($params_from_request[GD_URLPARAM_LIMIT])) {
                $limit = intval($params_from_request[GD_URLPARAM_LIMIT]);
                if ($limit === -1 || $limit === 0) {
                    $params_from_request[GD_URLPARAM_LIMIT] = $cmsengineapi->getOption(\PoP\LooseContracts\NameResolverFactory::getInstance()->getName('popcms:option:limit'));
                }
            }
            $params_from_request = HooksAPIFacade::getInstance()->applyFilters(
                'QueryDataModuleProcessorTrait:request:filter_params',
                $params_from_request
            );

            // Finally merge it into the data properties
            $data_properties[ParamConstants::QUERYARGS] = array_merge(
                $data_properties[ParamConstants::QUERYARGS],
                $params_from_request
            );
        }

        if ($queryHandlerClass = $this->getQueryHandlerClass($module)) {
            // Allow the queryhandler to override/normalize the query args
            $queryhandler = $instanceManager->getInstance($queryHandlerClass);
            $queryhandler->prepareQueryArgs($data_properties[ParamConstants::QUERYARGS]);
        }

        $dataloader = $instanceManager->getInstance($this->getDataloaderClass($module));
        return $dataloader->getDBObjectIDOrIDs($data_properties);
    }

    public function getDatasetmeta(array $module, array &$props, array $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDOrIDs): array
    {
        $ret = parent::getDatasetmeta($module, $props, $data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDOrIDs);

        if ($queryHandlerClass = $this->getQueryHandlerClass($module)) {
            $queryhandler = $instanceManager->getInstance($queryHandlerClass);

            if ($query_state = $queryhandler->getQueryState($data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDOrIDs)) {
                $ret['querystate'] = $query_state;
            }
            if ($query_params = $queryhandler->getQueryParams($data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDOrIDs)) {
                $ret['queryparams'] = $query_params;
            }
            if ($query_result = $queryhandler->getQueryResult($data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDOrIDs)) {
                $ret['queryresult'] = $query_result;
            }
        }

        return $ret;
    }
}
