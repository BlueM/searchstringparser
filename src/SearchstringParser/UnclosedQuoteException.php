<?php

namespace BlueM\SearchstringParser;

/**
 * Exception which is thrown when a search string contains a " which is not terminated
 */
class UnclosedQuoteException extends InvalidSyntaxException
{
    /**
     * {@inheritDoc}
     */
    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        parent::__construct('The search string contains an unclosed quote (incomplete phrase)');
    }
}
