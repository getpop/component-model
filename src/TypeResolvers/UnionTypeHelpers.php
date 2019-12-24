<?php
namespace PoP\ComponentModel\TypeResolvers;

use function substr;
use function explode;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;

class UnionTypeHelpers
{
    /**
     * If the type data resolver starts with "*" then it's union
     *
     * @param string $type
     * @return boolean
     */
    public static function isUnionType(string $type): bool
    {
        return substr($type, 0, strlen(UnionTypeSymbols::UNION_TYPE_NAME_PREFIX)) == UnionTypeSymbols::UNION_TYPE_NAME_PREFIX;
    }

    public static function getUnionTypeCollectionName(string $type): string
    {
        return UnionTypeSymbols::UNION_TYPE_NAME_PREFIX.$type;
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
            UnionTypeSymbols::DBOBJECT_COMPOSED_TYPE_ID_SEPARATOR,
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
            UnionTypeSymbols::DBOBJECT_COMPOSED_TYPE_ID_SEPARATOR,
            $composedDBObjectTypeAndID
        );
        // If the UnionTypeResolver didn't have a TypeResolver to process the passed object, the Type will not be added
        // In that case, the ID will be on the first position
        return count($elements) == 1 ? $elements[0] : $elements[1];
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
            $typeResolver->getTypeOutputName().
            UnionTypeSymbols::DBOBJECT_COMPOSED_TYPE_ID_SEPARATOR.
            $id;
    }

    public static function getUnionOrTargetTypeResolverClass(string $unionTypeResolverClass): ?string
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        // If there is more than 1 target type resolver for the Union, return the Union
        // If there is only one target, return that one directly and not the Union (since it's more efficient)
        // If there are none, return null
        $unionTypeResolver = $instanceManager->getInstance($unionTypeResolverClass);
        $targetTypeResolverClasses = $unionTypeResolver->getTargetTypeResolverClasses();
        if ($targetTypeResolverClasses) {
            return count($targetTypeResolverClasses) == 1 ?
                $targetTypeResolverClasses[0] :
                $unionTypeResolverClass;
        }
        return null;
    }
}
