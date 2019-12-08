<?php
namespace PoP\ComponentModel\TypeResolvers;

interface ConvertibleTypeResolverInterface
{
    public function addTypeToID($resultItemID): string;
    public function getTypeResolverClassForResultItem($resultItemID);
}
