<?php
namespace PoP\ComponentModel;

class Environment
{
    public static function removeFieldIfDirectiveFailed()
    {
        return isset($_ENV['REMOVE_FIELD_IF_DIRECTIVE_FAILED']) ? strtolower($_ENV['REMOVE_FIELD_IF_DIRECTIVE_FAILED']) == "true" : false;
    }
}

