<?php

namespace BlueM\SearchstringParser;

/**
 * Exception which is thrown when a search string contains a " which is not terminated
 */
class UnclosedQuoteException extends InvalidSyntaxException
{

}