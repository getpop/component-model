<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\DataloaderInterface;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

class SendByEmailDirectiveResolver extends AbstractGlobalDirectiveResolver
{
    public const DIRECTIVE_NAME = 'sendByEmail';
    public static function getDirectiveName(): string {
        return self::DIRECTIVE_NAME;
    }

    /**
     * By default, this directive goes after ResolveValueAndMerge
     *
     * @return void
     */
    public function getPipelinePosition(): string
    {
        return PipelinePositions::BACK;
    }

    /**
     * Most likely, this directive can be executed several times
     *
     * @return boolean
     */
    public function canExecuteMultipleTimesInField(): bool
    {
        return true;
    }

    // public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array
    // {
    //     $translationAPI = TranslationAPIFacade::getInstance();
    //     return [
    //         [
    //             SchemaDefinition::ARGNAME_NAME => 'to',
    //             SchemaDefinition::ARGNAME_TYPE => TypeCastingHelpers::combineTypes(SchemaDefinition::TYPE_ARRAY, SchemaDefinition::TYPE_EMAIL),
    //             SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Emails to send the email to', 'component-model'),
    //             SchemaDefinition::ARGNAME_MANDATORY => true,
    //         ],
    //         [
    //             SchemaDefinition::ARGNAME_NAME => 'subject',
    //             SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
    //             SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Email subject', 'component-model'),,
    //         ],
    //         [
    //             SchemaDefinition::ARGNAME_NAME => 'content',
    //             SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
    //             SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Email content', 'component-model'),
    //         ],
    //     ];
    // }

    public function resolveDirective(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages)
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $translationAPI = TranslationAPIFacade::getInstance();
        $dbKey = $dataloader->getDatabaseKey();
        foreach ($idsDataFields as $id => $dataFields) {
            foreach ($dataFields['direct'] as $field) {
                $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);
                // Validate that the property exists
                $isValueInDBItems = array_key_exists($fieldOutputKey, $dbItems[(string)$id] ?? []);
                if (!$isValueInDBItems && !array_key_exists($fieldOutputKey, $previousDBItems[$dbKey][(string)$id] ?? [])) {
                    if ($fieldOutputKey != $field) {
                        $dbErrors[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('Field \'%s\' (with output key \'%s\') hadn\'t been set for object with ID \'%s\', so it can\'t be transformed', 'component-model'),
                            $field,
                            $fieldOutputKey,
                            $id
                        );
                    } else {
                        $dbErrors[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('Field \'%s\' hadn\'t been set for object with ID \'%s\', so it can\'t be transformed', 'component-model'),
                            $fieldOutputKey,
                            $id
                        );
                    }
                    continue;
                }

                $value = $isValueInDBItems ?
                    $dbItems[(string)$id][$fieldOutputKey] :
                    $previousDBItems[$dbKey][(string)$id][$fieldOutputKey];

                // Validate that the value is an array
                if (!is_array($value)) {
                    if ($fieldOutputKey != $field) {
                        $dbErrors[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('The value for field \'%s\' (with output key \'%s\') is not an array, so execution of this directive can\'t continue', 'component-model'),
                            $field,
                            $fieldOutputKey,
                            $id
                        );
                    } else {
                        $dbErrors[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('The value for field \'%s\' is not an array, so execution of this directive can\'t continue', 'component-model'),
                            $fieldOutputKey,
                            $id
                        );
                    }
                    continue;
                }

                // Get the contents for the email, and validate
                $to = $value['to'];
                if (!$to) {
                    $dbErrors[(string)$id][$this->directive][] = sprintf(
                        $translationAPI->__('The \'to\' item in the array in field \'%s\' (with output key \'%s\') is empty, so the emails can\'t be sent', 'component-model'),
                        $field,
                        $fieldOutputKey,
                        $id
                    );
                    continue;
                }
                if (!is_array($to)) {
                    $to = [$to];
                }
                $content = $value['content'];
                $subject = $value['subject'];

                // We are not sending emails yet! Just add a new entry, with the contents to send
                $dbItems[(string)$id][$fieldOutputKey] = sprintf(
                    'to:%s; subject:%s; content:%s',
                    implode(',', $to),
                    $subject,
                    $content
                );
            }
        }
    }
}
