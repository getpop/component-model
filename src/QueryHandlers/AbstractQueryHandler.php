<?php
namespace PoP\ComponentModel\QueryHandlers;

abstract class AbstractQueryHandler implements QueryHandlerInterface
{
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
