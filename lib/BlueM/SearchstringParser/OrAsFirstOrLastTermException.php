<?php

namespace BlueM\SearchstringParser;

/**
 * Exception which is thrown when "OR" is the first or last term
 */
class OrAsFirstOrLastTermException extends InvalidSyntaxException
{
    protected $message = 'OR cannot be used as first or last term';
}
