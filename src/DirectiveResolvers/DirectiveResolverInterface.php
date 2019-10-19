<?php
namespace PoP\ComponentModel\DirectiveResolvers;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface DirectiveResolverInterface
{
    public static function getDirectiveName(): string;
    public function resolveDirective(FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations);
}
