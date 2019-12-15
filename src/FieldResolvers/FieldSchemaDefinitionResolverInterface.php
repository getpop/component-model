<?php
namespace PoP\ComponentModel\FieldResolvers;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

interface FieldSchemaDefinitionResolverInterface
{
    public function getSchemaFieldType(TypeResolverInterface $typeResolver, string $fieldName): ?string;
    public function getSchemaFieldDescription(TypeResolverInterface $typeResolver, string $fieldName): ?string;
    public function getSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): array;
    public function getSchemaFieldDeprecationDescription(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string;
    public function isOperatorOrHelper(TypeResolverInterface $typeResolver, string $fieldName): bool;
}
