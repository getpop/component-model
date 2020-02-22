<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\FieldResolvers\GlobalFieldResolverTrait;

abstract class AbstractGlobalFieldResolver extends AbstractDBDataFieldResolver
{
    use GlobalFieldResolverTrait;
}
