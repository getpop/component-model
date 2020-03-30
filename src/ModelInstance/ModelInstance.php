<?php
namespace PoP\ComponentModel\ModelInstance;
use PoP\Hooks\HooksAPIInterface;
use PoP\Definitions\Facades\DefinitionManagerFacade;
use PoP\ComponentModel\Info\ApplicationInfoInterface;
use PoP\Translation\TranslationAPIInterface;

class ModelInstance implements ModelInstanceInterface
{
    public const HOOK_COMPONENTS_RESULT = __CLASS__.':components:result';
    public const HOOK_COMPONENTSFROMVARS_POSTORGETCHANGE = __CLASS__.':componentsFromVars:postOrGetChange';
    public const HOOK_COMPONENTSFROMVARS_RESULT = __CLASS__.':componentsFromVars:result';

    protected $translationAPI;
    protected $hooksAPI;
    protected $applicationInfo;

    public function __construct(
        TranslationAPIInterface $translationAPI,
        HooksAPIInterface $hooksAPI,
        ApplicationInfoInterface $applicationInfo
    ) {
        $this->translationAPI = $translationAPI;
        $this->hooksAPI = $hooksAPI;
        $this->applicationInfo = $applicationInfo;
    }

    public function getModelInstanceId(): string
    {
        // The string is too long. Use a hashing function to shorten it
        return md5(implode('-', $this->getModelInstanceComponents()));
    }

    protected function getModelInstanceComponents(): array
    {
        $components = array();

        // Mix the information specific to the module, with that present in $vars
        $components = (array)$this->hooksAPI->applyFilters(
            self::HOOK_COMPONENTS_RESULT,
            array_merge(
                $components,
                $this->getModelInstanceComponentsFromVars()
            )
        );

        // Add the ones from package Definitions

	    // Comment Leo 05/04/2017: Also add the module-definition type, for 2 reasons:
	    // 1. It allows to create the 2 versions (DEV/PROD) of the configuration files, to compare/debug them side by side
	    // 2. It allows to switch from DEV/PROD without having to delete the pop-cache
        if ($definitionResolvers = DefinitionManagerFacade::getInstance()->getDefinitionResolvers()) {
            $resolvers = [];
            foreach ($definitionResolvers as $group => $resolverInstance) {
                $resolvers[] = $group.'-'.get_class($resolverInstance);
            }
            $components[] = $this->translationAPI->__('definition resolvers:', 'component-model').implode(',', $resolvers);
        }

        return $components;
    }

    protected function getModelInstanceComponentsFromVars(): array
    {
        $components = array();

        $vars = \PoP\ComponentModel\Engine_Vars::getVars();

        // There will always be a nature. Add it.
        $nature = $vars['nature'];
        $route = $vars['route'];
        $components[] = $this->translationAPI->__('nature:', 'component-model').$nature;
        $components[] = $this->translationAPI->__('route:', 'component-model').$route;

        // Add the version, because otherwise there may be PHP errors happening from stale configuration that is not deleted, and still served, after a new version is deployed
        $components[] = $this->translationAPI->__('version:', 'component-model').$vars['version'];

        // Other properties
        if ($format = $vars['format']) {
            $components[] = $this->translationAPI->__('format:', 'component-model').$format;
        }
        if ($target = $vars['target']) {
            $components[] = $this->translationAPI->__('target:', 'component-model').$target;
        }
        if ($actions = $vars['actions']) {
            $components[] = $this->translationAPI->__('actions:', 'component-model').implode(';', $actions);
        }
        if ($config = $vars['config']) {
            $components[] = $this->translationAPI->__('config:', 'component-model').$config;
        }
        if ($modulefilter = $vars['modulefilter']) {
            $components[] = $this->translationAPI->__('module filter:', 'component-model').$modulefilter;
        }
        if ($stratum = $vars['stratum']) {
            $components[] = $this->translationAPI->__('stratum:', 'component-model').$stratum;
        }

        // Can the configuration change when doing a POST or GET?
        if ($this->hooksAPI->applyFilters(
            self::HOOK_COMPONENTSFROMVARS_POSTORGETCHANGE,
            false
        )) {
            $components[] = $this->translationAPI->__('operation:', 'component-model').(doingPost() ? 'post' : 'get');
        }
        if ($mangled = $vars['mangled']) {
            // By default it is mangled. To make it non-mangled, url must have param "mangled=none",
            // so only in these exceptional cases the identifier will add this parameter
            $components[] = $this->translationAPI->__('mangled:', 'component-model').$mangled;
        }
        if ($vars['only-fieldname-as-outputkey']) {
            $components[] = $this->translationAPI->__('only-fieldname-as-outputkey', 'component-model');
        }

        // Allow for plug-ins to add their own vars. Eg: URE source parameter
        return (array)$this->hooksAPI->applyFilters(
            self::HOOK_COMPONENTSFROMVARS_RESULT,
            $components
        );
    }
}
