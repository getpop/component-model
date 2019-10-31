<?php
namespace PoP\ComponentModel\DataStructure;

abstract class AbstractJSONDataStructureFormatter extends AbstractDataStructureFormatter
{
    public function getContentType()
    {
        return 'application/json';
    }

    protected function printData(&$data)
    {
        echo json_encode($data, $this->getJsonEncodeType());
    }

    protected function getJsonEncodeType()
    {
        return null;
    }
}
