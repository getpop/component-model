<?php
namespace PoP\ComponentModel\TypeResolvers;

use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

class ConvertibleTypeHelpers
{
    /**
     * Extracts the DB key and ID from the resultItem ID
     *
     * @param array $composedDBKeyResultItemID
     * @return void
     */
    public static function extractDBKeyAndResultItemID(string $composedDBKeyResultItemID)
    {
        return explode(
            ConvertibleTypeSymbols::DBKEY_RESULTITEMID_SEPARATOR,
            $composedDBKeyResultItemID
        );
    }

    /**
     * Extracts the DB key and ID from the resultItem ID
     *
     * @param array $composedDBKeyResultItemID
     * @return void
     */
    public static function getComposedDBKeyAndResultItemID(TypeResolverInterface $typeResolver, $id)
    {
        $dbKey = $typeResolver->getDatabaseKey();
        return $dbKey.ConvertibleTypeSymbols::DBKEY_RESULTITEMID_SEPARATOR.$id;
    }
}
