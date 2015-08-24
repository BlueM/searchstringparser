<?php

namespace BlueM\SearchstringParser;

/**
 * Exception which is thrown when the search string contains contradictory instructions
 *
 * Example: "Foo NOT +Bar"
 */
class ContradictoryModifiersException extends InvalidSyntaxException
{
    protected $message = 'The search string contains contradictory instructions. (Examples: “-Word1 OR Word2”, “Word1 NOT +Word”)';
}
