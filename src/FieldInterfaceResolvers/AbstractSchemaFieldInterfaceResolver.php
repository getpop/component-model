<?php

declare(strict_types=1);

namespace PoP\ComponentModel\FieldInterfaceResolvers;

use PoP\ComponentModel\FieldResolvers\SelfSchemaDefinitionResolverTrait;

abstract class AbstractSchemaFieldInterfaceResolver extends AbstractFieldInterfaceResolver
{
    use SelfSchemaDefinitionResolverTrait;
}
