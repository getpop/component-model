<?php
namespace PoP\ComponentModel\TypeDataResolvers;

abstract class AbstractTypeDataResolver implements TypeDataResolverInterface
{
    public function getDataquery()
    {
        return null;
    }
}
