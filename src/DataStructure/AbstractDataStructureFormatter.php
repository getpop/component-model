<?php
namespace PoP\ComponentModel\DataStructure;

abstract class AbstractDataStructureFormatter implements DataStructureFormatterInterface
{
    public function getFormattedData($data)
    {
        return $data;
    }

    public function getJsonEncodeType()
    {
        return null;
    }

    public function getContentType()
    {
        return 'application/json';
    }

    public function outputResponse($data)
    {
        if ($contentType = $this->getContentType()) {
            header(
                sprintf(
                    'Content-type: %s',
                    $contentType
                )
            );
        }
        echo json_encode($data, $this->getJsonEncodeType());
    }
}
