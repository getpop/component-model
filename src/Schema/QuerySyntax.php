<?php
namespace PoP\ComponentModel\Schema;

class QuerySyntax {
    const SYMBOL_QUERYFIELDS_SEPARATOR = ',';
    const SYMBOL_FIELDPROPERTIES_SEPARATOR = '|';
    const SYMBOL_RELATIONALFIELDS_NEXTLEVEL = '.';
    const SYMBOL_FIELDARGS_OPENING = '(';
    const SYMBOL_FIELDARGS_CLOSING = ')';
    const SYMBOL_FIELDALIAS_PREFIX = '@';
    const SYMBOL_BOOKMARK_OPENING = '[';
    const SYMBOL_BOOKMARK_CLOSING = ']';
    const SYMBOL_FIELDDIRECTIVE_OPENING = '<';
    const SYMBOL_FIELDDIRECTIVE_CLOSING = '>';
    const SYMBOL_FIELDARGS_ARGSEPARATOR = ';';
    const SYMBOL_FIELDARGS_ARGKEYVALUESEPARATOR = ':';
    const SYMBOL_VARIABLE_PREFIX = '$';
    const SYMBOL_FRAGMENT_PREFIX = '--';
    const SYMBOL_FIELDARGS_ARGVALUESTRING_OPENING = '"';
    const SYMBOL_FIELDARGS_ARGVALUESTRING_CLOSING = '"';
    const SYMBOL_FIELDARGS_ARGVALUEARRAY_OPENING = '[';
    const SYMBOL_FIELDARGS_ARGVALUEARRAY_CLOSING = ']';
    const SYMBOL_FIELDARGS_ARGVALUEARRAY_SEPARATOR = ';';
    const TOKEN_BOOKMARK_PREV = 'prev';
}
