<?php
namespace PoP\ComponentModel\Schema;

use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\Environment;

trait WithVersionConstraintFieldOrDirectiveResolverTrait
{
    protected function getVersionConstraintSchemaFieldOrDirectiveArg(): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return [
            SchemaDefinition::ARGNAME_NAME => SchemaDefinition::ARGNAME_VERSION_CONSTRAINT,
            SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
            SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The version to restrict to, using the semantic versioning constraint rules used by Composer (https://getcomposer.org/doc/articles/versions.md)', 'component-model'),
        ];
    }

    /**
     * If enabled, add the "versionConstraint" param. Add it at the end, so it doesn't affect the order of params for "orderedSchemaDirectiveArgs"
     *
     * @param array $schemaDirectiveArgs
     * @return void
     */
    protected function maybeAddVersionConstraintSchemaFieldOrDirectiveArg(array &$schemaFieldOrDirectiveArgs): void
    {
        if (Environment::enableSemanticVersionConstraints()) {
            $schemaFieldOrDirectiveArgs[] = $this->getVersionConstraintSchemaFieldOrDirectiveArg();
        }
    }
}
