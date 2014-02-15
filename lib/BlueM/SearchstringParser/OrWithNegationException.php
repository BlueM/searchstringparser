<?php

namespace BlueM\SearchstringParser;

/**
 * Exception which is thrown when a negated term is "OR"-prefixed
 */
class OrWithNegationException extends InvalidSyntaxException
{

}