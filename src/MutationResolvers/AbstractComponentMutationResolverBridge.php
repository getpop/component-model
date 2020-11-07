<?php

declare(strict_types=1);

namespace PoP\ComponentModel\MutationResolvers;

use PoP\ComponentModel\ModuleProcessors\DataloadingConstants;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\QueryInputOutputHandlers\ResponseConstants;
use PoP\ComponentModel\MutationResolvers\MutationResolverInterface;
use PoP\ComponentModel\Facades\MutationResolution\MutationResolutionManagerFacade;
use PoP\ComponentModel\MutationResolvers\ComponentMutationResolverBridgeInterface;

abstract class AbstractComponentMutationResolverBridge implements ComponentMutationResolverBridgeInterface
{
    /**
     * @param mixed $result_id Maybe an int, maybe a string
     */
    public function getSuccessString($result_id): ?string
    {
        return null;
    }

    /**
     * @param mixed $result_id Maybe an int, maybe a string
     * @return string[]
     */
    public function getSuccessStrings($result_id): array
    {
        $success_string = $this->getSuccessString($result_id);
        return $success_string !== null ? [$success_string] : [];
    }

    protected function onlyExecuteWhenDoingPost(): bool
    {
        return true;
    }

    protected function returnIfError(): bool
    {
        return true;
    }

    protected function skipDataloadIfError(): bool
    {
        return false;
    }

    /**
     * @param array $data_properties
     * @return array<string, mixed>|null
     */
    public function execute(array &$data_properties): ?array
    {
        if ($this->onlyExecuteWhenDoingPost() && 'POST' !== $_SERVER['REQUEST_METHOD']) {
            return null;
        }
        $mutationResolverClass = $this->getMutationResolverClass();
        $instanceManager = InstanceManagerFacade::getInstance();
        /** @var MutationResolverInterface */
        $mutationResolver = $instanceManager->getInstance($mutationResolverClass);
        $form_data = $this->getFormData();
        $return = [];
        if ($errors = $mutationResolver->validate($form_data)) {
            $errorType = $mutationResolver->getErrorType();
            $errorTypeKeys = [
                ErrorTypes::STRINGS => ResponseConstants::ERRORSTRINGS,
                ErrorTypes::CODES => ResponseConstants::ERRORCODES,
            ];
            $return[$errorTypeKeys[$errorType]] = $errors;
            if ($this->skipDataloadIfError()) {
                // Bring no results
                $data_properties[DataloadingConstants::SKIPDATALOAD] = true;
            }
            if ($this->returnIfError()) {
                return $return;
            }
        }
        $errors = $errorcodes = [];
        $result_id = $mutationResolver->execute($errors, $errorcodes, $form_data);
        $this->modifyDataProperties($data_properties, $result_id);

        // Save the result for some module to incorporate it into the query args
        $gd_dataload_actionexecution_manager = MutationResolutionManagerFacade::getInstance();
        $gd_dataload_actionexecution_manager->setResult(get_called_class(), $result_id);

        $return[ResponseConstants::SUCCESS] = true;
        if ($success_strings = $this->getSuccessStrings($result_id)) {
            $return[ResponseConstants::SUCCESSSTRINGS] = $success_strings;
        }
        return $return;
    }

    /**
     * @param mixed $result_id Maybe an int, maybe a string
     */
    protected function modifyDataProperties(array &$data_properties, $result_id): void
    {
    }
}

