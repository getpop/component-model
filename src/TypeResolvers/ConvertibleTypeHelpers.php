<?php
namespace PoP\ComponentModel\TypeResolvers;

use function substr;
use function explode;
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
     * If the type data resolver starts with "*" then it's convertible
     *
     * @param string $dbKey
     * @return boolean
     */
    public static function isConvertibleDBKey(string $dbKey): bool
    {
        return substr($dbKey, 0, strlen(ConvertibleTypeSymbols::DBKEY_NAME_PREFIX)) == ConvertibleTypeSymbols::DBKEY_NAME_PREFIX;
    }

    public static function getConvertibleDatabaseKey(string $dbKey): string
    {
        return ConvertibleTypeSymbols::DBKEY_NAME_PREFIX.$dbKey;
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
