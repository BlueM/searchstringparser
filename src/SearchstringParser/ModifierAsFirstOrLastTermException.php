<?php

namespace BlueM\SearchstringParser;

/**
 * Exception which is thrown when @todo
 */
class ModifierAsFirstOrLastTermException extends InvalidSyntaxException
{
    /**
     * {@inheritDoc}
     */
    public function __construct($message = '', $code = 0, Exception $previous = null)
    {
        parent::__construct('The search string must neither start nor end with “AND” or “OR”.');
    }
}
