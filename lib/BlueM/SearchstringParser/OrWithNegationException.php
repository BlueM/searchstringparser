<?php

namespace BlueM\SearchstringParser;

/**
 * Exception which is thrown when a negated term is "OR"-prefixed
 */
class OrWithNegationException extends InvalidSyntaxException
{
    protected $message = 'Cannot use OR with a term that is negated using - or NOT';
}
