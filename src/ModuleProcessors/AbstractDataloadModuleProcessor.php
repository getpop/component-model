<?php
namespace PoP\ComponentModel\ModuleProcessors;

abstract class AbstractDataloadModuleProcessor extends AbstractQueryDataModuleProcessor implements DataloadingModuleInterface
{
    use DataloadModuleProcessorTrait;
}
