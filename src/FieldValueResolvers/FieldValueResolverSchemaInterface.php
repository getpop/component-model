<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface FieldValueResolverSchemaInterface
{
    public function getSchemaFieldType(FieldResolverInterface $fieldResolver, string $fieldName): ?string;
    public function getSchemaFieldDescription(FieldResolverInterface $fieldResolver, string $fieldName): ?string;
    public function getSchemaFieldArgs(FieldResolverInterface $fieldResolver, string $fieldName): array;
    public function getSchemaFieldDeprecationDescription(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): ?string;
    public function isOperatorOrHelper(FieldResolverInterface $fieldResolver, string $fieldName): bool;
}
