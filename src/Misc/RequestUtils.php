<?php
namespace PoP\ComponentModel\Misc;

use PoP\Hooks\Facades\HooksAPIFacade;
use PoP\ComponentModel\ModuleFilters\ModuleFilterManager;
use PoP\ComponentModel\ModuleFilters\ModulePaths;
use PoP\ComponentModel\Misc\GeneralUtils;
use PoP\ComponentModel\Configuration\Request;
use PoP\ComponentModel\State\ApplicationState;

class RequestUtils
{
    public static $errors = array();

    public static function getDomainId($domain)
    {
        // The domain ID is simply removing the scheme, and replacing all dots with '-'
        // It is needed to assign an extra class to the event
        $domain_id = str_replace('.', '-', removeScheme($domain));

        // Allow to override the domainId, to unify DEV and PROD domains
        return HooksAPIFacade::getInstance()->applyFilters('pop_modulemanager:domain_id', $domain_id, $domain);
    }

    public static function isSearchEngine()
    {
        return HooksAPIFacade::getInstance()->applyFilters('RequestUtils:isSearchEngine', false);
    }

    // // public static function getCheckpointConfiguration($page_id = null) {

    // //     return Settings\SettingsManagerFactory::getInstance()->getCheckpointConfiguration($page_id);
    // // }
    // public static function getCheckpoints($page_id = null) {

    //     return Settings\SettingsManagerFactory::getInstance()->getCheckpoints($page_id);
    // }

    // public static function isServerAccessMandatory($checkpoint_configuration) {

    //     // The Static type can be cached since it contains no data
    //     $dynamic_types = array(
    //         GD_DATALOAD_VALIDATECHECKPOINTS_TYPE_DATAFROMSERVER,
    //     );
    //     $mandatory = in_array($checkpoint_configuration['type'], $dynamic_types);

    //     // Allow to add 'requires-user-state' by PoP UserState dependency
    //     return HooksAPIFacade::getInstance()->applyFilters(
    //         'RequestUtils:isServerAccessMandatory',
    //         $mandatory,
    //         $checkpoint_configuration
    //     );
    // }

    // public static function checkpointValidationRequired($checkpoint_configuration) {

    //     return true;
    //     // $type = $checkpoint_configuration['type'];
    //     // return (doingPost() && $type == GD_DATALOAD_VALIDATECHECKPOINTS_TYPE_STATIC) || $type == GD_DATALOAD_VALIDATECHECKPOINTS_TYPE_DATAFROMSERVER || $type == GD_DATALOAD_VALIDATECHECKPOINTS_TYPE_STATELESS;
    // }

    public static function getCurrentUrl()
    {
        // Strip the Target and Output off it, users don't need to see those
        $remove_params = (array) HooksAPIFacade::getInstance()->applyFilters(
            'RequestUtils:current_url:remove_params',
            array(
                \GD_URLPARAM_SETTINGSFORMAT,
                \GD_URLPARAM_VERSION,
                \GD_URLPARAM_TARGET,
                ModuleFilterManager::URLPARAM_MODULEFILTER,
                ModulePaths::URLPARAM_MODULEPATHS,
                \GD_URLPARAM_ACTIONPATH,
                \GD_URLPARAM_DATAOUTPUTITEMS,
                \GD_URLPARAM_DATASOURCES,
                \GD_URLPARAM_DATAOUTPUTMODE,
                \GD_URLPARAM_DATABASESOUTPUTMODE,
                \GD_URLPARAM_OUTPUT,
                \GD_URLPARAM_DATASTRUCTURE,
                Request::URLPARAM_MANGLED,
                \GD_URLPARAM_EXTRAROUTES,
                \GD_URLPARAM_ACTIONS, // Needed to remove ?actions[]=preload, ?actions[]=loaduserstate, ?actions[]=loadlazy
                \GD_URLPARAM_STRATUM,
            )
        );
        $url = GeneralUtils::removeQueryArgs($remove_params, fullUrl());

        // Allow plug-ins to do their own logic to the URL
        $url = HooksAPIFacade::getInstance()->applyFilters('RequestUtils:getCurrentUrl', $url);

        return urldecode($url);
    }

    public static function getURLPath()
    {
        // Allow to remove the language information from qTranslate (https://domain.com/en/...)
        $route = HooksAPIFacade::getInstance()->applyFilters(
            '\PoP\Routing:uri-route',
            $_SERVER['REQUEST_URI']
        );
        $params_pos = strpos($route, '?');
        if ($params_pos !== false) {
            $route = substr($route, 0, $params_pos);
        }
        return trim($route, '/');
    }

    public static function getFramecomponentModules()
    {
        return HooksAPIFacade::getInstance()->applyFilters(
            'RequestUtils:getFramecomponentModules',
            array()
        );
    }

    public static function addRoute($url, $route)
    {
        return GeneralUtils::addQueryArgs([\GD_URLPARAM_ROUTE => $route], $url);
    }

    public static function fetchingSite()
    {
        $vars = ApplicationState::getVars();
        return $vars['fetching-site'];
    }

    public static function loadingSite()
    {
        // If we are doing JSON (or any other output) AND we setting the target, then we're loading content dynamically and we need it to be JSON
        // Otherwise, it is the first time loading website => loadingSite
        $vars = ApplicationState::getVars();
        return $vars['loading-site'];
    }

    public static function isRoute($route_or_routes)
    {
        $vars = ApplicationState::getVars();
        $route = $vars['route'];
        if (is_array($route_or_routes)) {
            return in_array($route, $route_or_routes);
        }

        return $route == $route_or_routes;
    }
}
