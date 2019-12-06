<?php
namespace PoP\ComponentModel\TypeDataResolvers;

use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;

abstract class AbstractTypeDataResolver implements TypeDataResolverInterface
{
    public function getDatabaseKey()
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $typeResolverClass = $this->getTypeResolverClass();
        $typeResolver = $instanceManager->getInstance($typeResolverClass);
        return $typeResolver->getDatabaseKey();
    }

    public function getDataquery()
    {
        return null;
    }
}
