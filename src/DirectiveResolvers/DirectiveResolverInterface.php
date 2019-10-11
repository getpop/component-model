<?php
namespace PoP\ComponentModel\DirectiveResolvers;

interface DirectiveResolverInterface
{
    // public function getDirectiveName(): string;
    public function resolveDirective($fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations);
}
