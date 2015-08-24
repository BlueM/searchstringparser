<?php

namespace BlueM\SearchstringParser;

/**
 * Exception which is thrown when @todo
 */
class ModifierAsFirstOrLastTermException extends InvalidSyntaxException
{
    protected $message = 'The search string must neither start nor end with “AND” or “OR”.';
}
