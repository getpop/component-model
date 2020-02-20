<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\Feedback\Tokens;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\Facades\Engine\EngineFacade;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\DirectiveResolvers\ValidateDirectiveResolver;
use PoP\ComponentModel\GeneralUtils;

abstract class AbstractValidateCheckpointDirectiveResolver extends ValidateDirectiveResolver
{
    abstract protected function getValidationCheckpointSet(TypeResolverInterface $typeResolver): array;

    protected function getValidationCheckpointErrorMessage(TypeResolverInterface $typeResolver, array $failedDataFields): string
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

    /**
     * Validate checkpoints
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
        $checkpointSet = $this->getValidationCheckpointSet($typeResolver);
        $engine = EngineFacade::getInstance();
        $validation = $engine->validateCheckpoints($checkpointSet);
        if (GeneralUtils::isError($validation)) {
            // All fields failed
            $failedDataFields = array_merge(
                $failedDataFields,
                $dataFields
            );
            $schemaErrors[] = [
                Tokens::PATH => $dataFields,
                Tokens::MESSAGE => $this->getValidationCheckpointErrorMessage($typeResolver, $dataFields),
            ];
        }
    }
}
