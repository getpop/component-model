<?php
namespace PoP\ComponentModel\DataStructure;

abstract class AbstractDataStructureFormatter implements DataStructureFormatterInterface
{
    public function getFormattedData($data)
    {
        return $data;
    }

    public function outputResponse(&$data)
    {
        $this->sendHeaders();
        $this->printData($data);
    }

    protected function sendHeaders()
    {
        if ($contentType = $this->getContentType()) {
            header(
                sprintf(
                    'Content-type: %s',
                    $contentType
                )
            );
        }
    }

    protected function printData(&$data)
    {
        echo $data;
    }
}
