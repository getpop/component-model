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
    abstract public function getSuccessString($result_id): string;

    /**
     * @param array $data_properties
     * @return array<string, mixed>|null
     */
    public function execute(array &$data_properties): ?array
    {
        if ('POST' == $_SERVER['REQUEST_METHOD']) {
            $mutationResolverClass = $this->getMutationResolverClass();
            $instanceManager = InstanceManagerFacade::getInstance();
            /** @var MutationResolverInterface */
            $mutationResolver = $instanceManager->getInstance($mutationResolverClass);
            $errors = array();
            $result_id = $mutationResolver->execute($errors);

            if ($errors) {
                // Bring no results
                $data_properties[DataloadingConstants::SKIPDATALOAD] = true;
                return array(
                    ResponseConstants::ERRORSTRINGS => $errors
                );
            }

            $this->modifyDataProperties($data_properties, $result_id);

            // Save the result for some module to incorporate it into the query args
            $gd_dataload_actionexecution_manager = MutationResolutionManagerFacade::getInstance();
            $gd_dataload_actionexecution_manager->setResult(get_called_class(), $result_id);

            // No errors => success
            $success_string = $this->getSuccessString($result_id);
            return array(
                ResponseConstants::SUCCESS => true,
                ResponseConstants::SUCCESSSTRINGS => array($success_string)
            );
        }

        return null;
    }

    /**
     * @param mixed $result_id Maybe an int, maybe a string
     */
    public function modifyDataProperties(array &$data_properties, $result_id): void
    {
        // Modify the block-data-settings, saying to select the id of the newly created post
        $data_properties[DataloadingConstants::QUERYARGS]['include'] = array($result_id);
    }

    abstract public function getMutationResolverClass(): string;
}

