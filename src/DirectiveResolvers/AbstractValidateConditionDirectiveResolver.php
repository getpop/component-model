<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\Feedback\Tokens;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\DirectiveResolvers\AbstractValidateDirectiveResolver;

abstract class AbstractValidateConditionDirectiveResolver extends AbstractValidateDirectiveResolver
{
    /**
     * Validate a custom condition
     *
     * @param TypeResolverInterface $typeResolver
     * @param array $dataFields
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @param array $variables
     * @return void
     */
    protected function validateFields(TypeResolverInterface $typeResolver, array $dataFields, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$variables, array &$failedDataFields): void
    {
        if (!$this->validateCondition($typeResolver)) {
            // All fields failed
            $failedDataFields = array_merge(
                $failedDataFields,
                $dataFields
            );
            $schemaErrors[] = [
                Tokens::PATH => $dataFields,
                Tokens::MESSAGE => $this->getValidationFailedMessage($typeResolver, $dataFields),
            ];
        }
    }

    /**
     * Condition to validate. Return `true` for success, `false` for failure
     *
     * @param TypeResolverInterface $typeResolver
     * @return boolean
     */
    abstract protected function validateCondition(TypeResolverInterface $typeResolver): bool;

    protected function getValidationFailedMessage(TypeResolverInterface $typeResolver, array $failedDataFields): string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return sprintf(
            $translationAPI->__('Validation failed for fields \'%s\'', 'component-model'),
            implode(
                $translationAPI->__('\', \''),
                $failedDataFields
            )
        );
    }
}
