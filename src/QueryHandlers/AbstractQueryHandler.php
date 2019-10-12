<?php
namespace PoP\ComponentModel;
use PoP\ComponentModel\Facades\Managers\QueryHandlerManagerFacade;

abstract class AbstractQueryHandler
{
    public function __construct()
    {
        $queryhandler_manager = QueryHandlerManagerFacade::getInstance();
        $queryhandler_manager->add($this->getName(), $this);
    }

    abstract public function getName();

    public function prepareQueryArgs(&$query_args)
    {
    }

    public function getQueryState($data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDOrIDs): array
    {
        return array();
    }
    public function getQueryParams($data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDOrIDs): array
    {
        return array();
    }
    public function getQueryResult($data_properties, $dataaccess_checkpoint_validation, $actionexecution_checkpoint_validation, $executed, $dbObjectIDOrIDs): array
    {
        return array();
    }
}
