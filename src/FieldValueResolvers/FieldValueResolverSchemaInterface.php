<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface FieldValueResolverSchemaInterface
{
    public function getFieldDocumentation(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): array;
    public function getFieldDocumentationType(FieldResolverInterface $fieldResolver, string $fieldName): ?string;
    public function getFieldDocumentationDescription(FieldResolverInterface $fieldResolver, string $fieldName): ?string;
    public function getFieldDocumentationArgs(FieldResolverInterface $fieldResolver, string $fieldName): array;
    public function getFieldDocumentationDeprecationDescription(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): ?string;
}
