<?php
namespace PoP\ComponentModel\ModuleProcessors;

use PoP\ComponentModel\Schema\SchemaDefinition;

trait DataloadQueryArgsFilterModuleProcessorTrait
{
    /**
     * Return an array of elements, instead of a single element, to enable filters with several inputs (such as "date", with inputs "date-from" and "date-to") to document all of them
     *
     * @param array $module
     * @return array
     */
    public function getFilterSchemaDefinitionItems(array $module): array
    {
    	$documentationItems = [
            $this->getFilterSchemaDefinition($module),
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

    public function getFilterSchemaDefinitionResolver(array $module): ?DataloadQueryArgsSchemaFilterModuleProcessorInterface
    {
        return null;
    }

    /**
     * Documentation for the module
     *
     * @param array $module
     * @return array
     */
    protected function getFilterSchemaDefinition(array $module): array
    {
    	$documentation = [
    		SchemaDefinition::ARGNAME_NAME => $this->getName($module),
        ];
        if ($filterSchemaDefinitionResolver = $this->getFilterSchemaDefinitionResolver($module)) {
            if ($type = $filterSchemaDefinitionResolver->getFilterDocumentationType($module)) {
                $documentation[SchemaDefinition::ARGNAME_TYPE] = $type;
            }
            if ($description = $filterSchemaDefinitionResolver->getFilterDocumentationDescription($module)) {
                $documentation[SchemaDefinition::ARGNAME_DESCRIPTION] = $description;
            }
            if ($filterSchemaDefinitionResolver->getFilterDocumentationMandatory($module)) {
                $documentation[SchemaDefinition::ARGNAME_MANDATORY] = true;
            }
            if ($deprecationDescription = $filterSchemaDefinitionResolver->getFilterDocumentationDeprecationDescription($module)) {
                $documentation[SchemaDefinition::ARGNAME_DEPRECATED] = true;
                $documentation[SchemaDefinition::ARGNAME_DEPRECATEDDESCRIPTION] = $deprecationDescription;
            }
        }
        return $documentation;
    }
}
