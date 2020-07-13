<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Enums;

use PoP\ComponentModel\Schema\SchemaHelpers;
use PoP\ComponentModel\State\ApplicationState;

abstract class AbstractEnum implements EnumInterface
{
    final public function getName(): string
    {
        return $this->getMaybeNamespacedName();
    }
    public function getNamespace(): string
    {
        return SchemaHelpers::getSchemaNamespace(__NAMESPACE__);
    }
    final public function getNamespacedName(): string
    {
        return SchemaHelpers::getSchemaNamespacedName(
            $this->getNamespace(),
            $this->getEnumName()
        );
    }
    final public function getMaybeNamespacedName(): string
    {
        $vars = ApplicationState::getVars();
        return $vars['namespace-types-and-interfaces'] ?
            $this->getNamespacedName() :
            $this->getEnumName();
    }

    /**
     * Enum name
     *
     * @return string
     */
    abstract protected function getEnumName(): string;
}
