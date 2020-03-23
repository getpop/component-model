<?php
namespace PoP\ComponentModel\Schema;

use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;

trait WithVersionConstraintFieldOrDirectiveResolverTrait
{
    protected function getVersionConstraintSchemaFieldArg(): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return [
            SchemaDefinition::ARGNAME_NAME => SchemaDefinition::ARGNAME_VERSION_CONSTRAINT,
            SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
            SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The version to restrict to, using the semantic versioning constraint rules used by Composer (https://getcomposer.org/doc/articles/versions.md)', 'component-model'),
        ];
    }
}
