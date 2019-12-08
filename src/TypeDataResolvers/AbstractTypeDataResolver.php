<?php
namespace PoP\ComponentModel\TypeDataResolvers;

use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;

abstract class AbstractTypeDataResolver implements TypeDataResolverInterface
{
    public function getDatabaseKey(): string
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        $typeResolverClass = $this->getTypeResolverClass();
        $typeResolver = $instanceManager->getInstance($typeResolverClass);
        return $typeResolver->getTypeCollectionName();
    }

    public function getDataquery()
    {
        return null;
    }
}
