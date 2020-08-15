<?php

namespace BlueM\SearchstringParser;

/**
 * Exception which is thrown when "NOT" is the last term
 */
class NotAsLastTermException extends InvalidSyntaxException
{
    /**
     * {@inheritDoc}
     */
    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        parent::__construct('The search string must not end with “NOT”');
    }
}
