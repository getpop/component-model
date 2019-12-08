<?php
namespace PoP\ComponentModel\TypeResolvers;

use function strpos;
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
     * If param $maybeComposedDBKeyResultItemID contains a "/" then it's a composition of dbKey/resultItemID
     * Then, extract them
     * Otherwise, return the same elements
     *
     * @param array $composedDBKeyResultItemID
     * @return void
     */
    public static function maybeExtractDBKeyAndResultItemID(string $dbKey, string $maybeComposedDBKeyResultItemID)
    {
        if (is_string($maybeComposedDBKeyResultItemID) && strpos($maybeComposedDBKeyResultItemID, ConvertibleTypeSymbols::DBKEY_RESULTITEMID_SEPARATOR) !== false) {
            $composedDBKeyResultItemID = $maybeComposedDBKeyResultItemID;
            return self::extractDBKeyAndResultItemID($composedDBKeyResultItemID);
        }
        $resultItemID = $maybeComposedDBKeyResultItemID;
        return [
            $dbKey,
            $resultItemID
        ];
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
