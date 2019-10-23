<?php
namespace PoP\ComponentModel\Configuration;

class Request
{
    const URLPARAM_MANGLED = 'mangled';
    const URLPARAMVALUE_MANGLED_NONE = 'none';

    public static function isMangled()
    {
        // By default, it is mangled, if not mangled then param "mangled" must have value "none"
        // Coment Leo 13/01/2017: getVars() can't function properly since it references objects which have not been initialized yet,
        // when called at the very beginning. So then access the request directly
        return !$_REQUEST[self::URLPARAM_MANGLED] || $_REQUEST[self::URLPARAM_MANGLED] != self::URLPARAMVALUE_MANGLED_NONE;
    }
}

