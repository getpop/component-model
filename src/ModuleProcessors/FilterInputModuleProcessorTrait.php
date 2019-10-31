<?php
namespace PoP\ComponentModel\ModuleProcessors;

use PoP\ComponentModel\Schema\SchemaDefinition;

trait FilterInputModuleProcessorTrait
{
    /**
     * Return an array of elements, instead of a single element, to enable filters with several inputs (such as "date", with inputs "date-from" and "date-to") to document all of them
     *
     * @param array $module
     * @return array
     */
    public function getFilterInputSchemaDefinitionItems(array $module): array
    {
    	$documentationItems = [
            $this->getFilterInputSchemaDefinition($module),
        ];
        $this->modifyFilterSchemaDefinitionItems($documentationItems, $module);
    	return $documentationItems;
    }

    /**
     * Function to override
     *
     * @param array $documentationItems
     * @param array $module
     * @return void
     */
    protected function modifyFilterSchemaDefinitionItems(array &$documentationItems, array $module)
    {
    }

    public function getFilterInputSchemaDefinitionResolver(array $module): ?DataloadQueryArgsSchemaFilterInputModuleProcessorInterface
    {
        return null;
    }

    /**
     * Documentation for the module
     *
     * @param array $module
     * @return array
     */
    protected function getFilterInputSchemaDefinition(array $module): array
    {
    	$documentation = [
    		SchemaDefinition::ARGNAME_NAME => $this->getName($module),
        ];
        if ($filterSchemaDefinitionResolver = $this->getFilterInputSchemaDefinitionResolver($module)) {
            if ($type = $filterSchemaDefinitionResolver->getSchemaFilterInputType($module)) {
                $documentation[SchemaDefinition::ARGNAME_TYPE] = $type;
            }
            if ($description = $filterSchemaDefinitionResolver->getSchemaFilterInputDescription($module)) {
                $documentation[SchemaDefinition::ARGNAME_DESCRIPTION] = $description;
            }
            if ($filterSchemaDefinitionResolver->getSchemaFilterInputMandatory($module)) {
                $documentation[SchemaDefinition::ARGNAME_MANDATORY] = true;
            }
            if ($deprecationDescription = $filterSchemaDefinitionResolver->getSchemaFilterInputDeprecationDescription($module)) {
                $documentation[SchemaDefinition::ARGNAME_DEPRECATED] = true;
                $documentation[SchemaDefinition::ARGNAME_DEPRECATEDDESCRIPTION] = $deprecationDescription;
            }
        }
        return $documentation;
    }
}
