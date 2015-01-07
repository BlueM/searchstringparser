<?php

namespace BlueM\SearchstringParser;

/**
 * Exception which is thrown when "NOT" is the last term
 */
class NotAsLastTermException extends InvalidSyntaxException
{
    protected $message = 'NOT cannot be used as last term';
}
