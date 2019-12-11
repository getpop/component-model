<?php
namespace PoP\ComponentModel\TypeDataLoaders;

abstract class AbstractTypeDataLoader implements TypeDataLoaderInterface
{
    public function getDataquery()
    {
        return null;
    }
}
