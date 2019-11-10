<?php
namespace PoP\ComponentModel\DirectiveResolvers;

class ForEachDirectiveResolver extends AbstractApplyNestedDirectivesOnArrayItemsDirectiveResolver
{
    public const DIRECTIVE_NAME = 'forEach';
    public static function getDirectiveName(): string {
        return self::DIRECTIVE_NAME;
    }

    /**
     * Iterate on all items from the array
     *
     * @param array $value
     * @return void
     */
    protected function getArrayItems(array $value): array
    {
        return $value;
    }
}
