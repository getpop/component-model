<?php
namespace PoP\ComponentModel\TypeResolvers;

use function substr;
use function explode;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

class ConvertibleTypeHelpers
{
    /**
     * If the type data resolver starts with "*" then it's convertible
     *
     * @param string $type
     * @return boolean
     */
    public static function isConvertibleType(string $type): bool
    {
        return substr($type, 0, strlen(ConvertibleTypeSymbols::CONVERTIBLE_TYPE_NAME_PREFIX)) == ConvertibleTypeSymbols::CONVERTIBLE_TYPE_NAME_PREFIX;
    }

    public static function getConvertibleTypeCollectionName(string $type): string
    {
        return ConvertibleTypeSymbols::CONVERTIBLE_TYPE_NAME_PREFIX.$type;
    }

    /**
     * Extracts the DB key and ID from the resultItem ID
     *
     * @param array $composedDBKeyResultItemID
     * @return void
     */
    public static function extractDBObjectTypeAndID(string $composedDBKeyResultItemID)
    {
        return explode(
            ConvertibleTypeSymbols::DBOBJECT_COMPOSED_TYPE_ID_SEPARATOR,
            $composedDBKeyResultItemID
        );
    }

    /**
     * Extracts the ID from the resultItem ID
     *
     * @param array $composedDBKeyResultItemID
     * @return void
     */
    public static function extractDBObjectID(string $composedDBObjectTypeAndID)
    {
        $elements = explode(
            ConvertibleTypeSymbols::DBOBJECT_COMPOSED_TYPE_ID_SEPARATOR,
            $composedDBObjectTypeAndID
        );
        return $elements[1];
    }

    /**
     * Creates a composed string containing the type and ID of the dbObject
     *
     * @param array $composedDBKeyResultItemID
     * @return void
     */
    public static function getDBObjectComposedTypeAndID(TypeResolverInterface $typeResolver, $id): string
    {
        return
            $typeResolver->getTypeName().
            ConvertibleTypeSymbols::DBOBJECT_COMPOSED_TYPE_ID_SEPARATOR.
            $id;
    }
}
