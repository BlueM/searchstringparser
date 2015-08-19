<?php

namespace BlueM;

use BlueM\SearchstringParser\NotAsLastTermException;
use BlueM\SearchstringParser\OrAsFirstOrLastTermException;
use BlueM\SearchstringParser\UnclosedQuoteException;
use BlueM\SearchstringParser\OrWithNegationException;

require __DIR__ . '/../lib/BlueM/SearchstringParser.php';
require __DIR__ . '/../lib/BlueM/SearchstringParser/InvalidSyntaxException.php';
require __DIR__ . '/../lib/BlueM/SearchstringParser/UnclosedQuoteException.php';
require __DIR__ . '/../lib/BlueM/SearchstringParser/NotAsLastTermException.php';
require __DIR__ . '/../lib/BlueM/SearchstringParser/OrAsFirstOrLastTermException.php';
require __DIR__ . '/../lib/BlueM/SearchstringParser/OrWithNegationException.php';

/**
 * Unit tests for BlueM\SearchstringParser
 */
class SearchstringParserTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Empty search string
     */
    public function theConstructorThrowsAnExceptionIfTheSearchStringIsEmpty()
    {
        new SearchstringParser('');
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid minimum length
     */
    public function theConstructorThrowsAnExceptionIfTheMinimumLengthIsNotAnInt()
    {
        new SearchstringParser('Hello World', array('minlength' => 'abc'));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid minimum length
     */
    public function theConstructorThrowsAnExceptionIfTheMinimumLengthIsSmallerThan1()
    {
        new SearchstringParser('Hello World', array('minlength' => 0));
    }

    /**
     * @test
     */
    public function theConstructorAcceptsAValidMinimumLength()
    {
        $search = new SearchstringParser('Hello World', array('minlength' => 2));

        $optionsProperty = new \ReflectionProperty($search, 'options');
        $optionsProperty->setAccessible(true);
        $options = $optionsProperty->getValue($search);

        $this->assertSame(2, $options['minlength']);
    }

    /**
     * @test
     */
    public function theAndTermsAreReturnedByTheGetter()
    {
        $search = new SearchstringParser('Hello World');

        $termsProperty = new \ReflectionProperty($search, 'andTerms');
        $termsProperty->setAccessible(true);
        $termsProperty->setValue($search, array('term1', 'term2'));

        $this->assertSame(array('term1', 'term2'), $search->getAndTerms());
    }

    /**
     * @test
     */
    public function theOrTermsAreReturnedByTheGetter()
    {
        $search = new SearchstringParser('Hello World');

        $termsProperty = new \ReflectionProperty($search, 'orTerms');
        $termsProperty->setAccessible(true);
        $termsProperty->setValue($search, array('term1', 'term2'));

        $this->assertSame(array('term1', 'term2'), $search->getOrTerms());
    }

    /**
     * @test
     */
    public function theNotTermsAreReturnedByTheGetter()
    {
        $search = new SearchstringParser('Hello World');

        $exclusionTermsProperty = new \ReflectionProperty($search, 'notTerms');
        $exclusionTermsProperty->setAccessible(true);
        $exclusionTermsProperty->setValue($search, array('term1', 'term2'));

        $this->assertSame(array('term1', 'term2'), $search->getNotTerms());
    }

    /**
     * @test
     */
    public function theSkippedTermsAreReturnedByTheGetter()
    {
        $search = new SearchstringParser('Hello World');

        $skippedProperty = new \ReflectionProperty($search, 'skipped');
        $skippedProperty->setAccessible(true);
        $skippedProperty->setValue($search, array('term1', 'term2'));

        $this->assertSame(array('term1', 'term2'), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function theExceptionsAreReturnedByTheGetter()
    {
        $search    = new SearchstringParser('Hello World');
        $exception = new NotAsLastTermException();

        $exceptionsProperty = new \ReflectionProperty($search, 'exceptions');
        $exceptionsProperty->setAccessible(true);
        $exceptionsProperty->setValue($search, array($exception));

        $this->assertSame(array($exception), $search->getExceptions());
    }

    /**
     * @test
     */
    public function allTermsInASearchStringWithoutModifiersAreRegardedAsRequired()
    {
        $search = new SearchstringParser('Hello World');

        $this->assertSame(array('Hello', 'World'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function aMinusPrefixExcludesTheFollowingTerm()
    {
        $search = new SearchstringParser('Hello -World');

        $this->assertSame(array('Hello'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array('World'), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function aNotExcludesTheFollowingTerm()
    {
        $search = new SearchstringParser('Hello NOT World');

        $this->assertSame(array('Hello'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array('World'), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function anAndMakesTheAdjacentTermsRequired()
    {
        $search = new SearchstringParser('Hello AND World AnotherString');

        $this->assertSame(array('Hello', 'World', 'AnotherString'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function anOrMakesThePreviousAndNextTermOptional()
    {
        $search = new SearchstringParser('term1 OR term2');

        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('term1', 'term2'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function phrasesCanBeDefinedUsingQuotes()
    {
        $search = new SearchstringParser('test "Hello World"');

        $this->assertSame(array('test', 'Hello World'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function escapedQuotesInsidePhrasesCanBeUsed()
    {
        $search = new SearchstringParser('"Hello \" World"');

        $this->assertSame(array('Hello " World'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function phrasesCanBeExcludedByPrefixingThemWithAMinus()
    {
        $search = new SearchstringParser('test -"Hello World"');

        $this->assertSame(array('test'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array('Hello World'), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function aTermWhichIsShorterThanTheDefinedMinimumIsSkipped()
    {
        $search = new SearchstringParser('AB C');

        $this->assertSame(array('AB'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array('C'), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function aCombinationOfSupportedSyntaxesIsParsedCorrectly()
    {
        $search = new SearchstringParser('"search string parser" "PHP 5.4" OR "PHP 5.3" NOT "PHP 4" NOT C# -C++ C');

        $this->assertSame(array('search string parser'), $search->getAndTerms());
        $this->assertSame(array('PHP 5.4', 'PHP 5.3'), $search->getOrTerms());
        $this->assertSame(array('PHP 4', 'C#', 'C++'), $search->getNotTerms());
        $this->assertSame(array('C'), $search->getSkippedTerms());
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\UnclosedQuoteException
     */
    public function anUnclosedPhraseThrowsAnExceptionIfTheThrowOptionIsSet()
    {
        new SearchstringParser('Hello "World', array('throw' => true));
    }

    /**
     * @test
     */
    public function anUnclosedPhraseEndsSilentlyAtTheEndOfStringIfTheThrowOptionIsNotSet()
    {
        $search = new SearchstringParser('Test "Hello World');
        $this->assertSame(array('Test', 'Hello World'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof UnclosedQuoteException);
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\NotAsLastTermException
     * @expectedExceptionMessage NOT cannot be used as last term
     */
    public function ifTheLastTermIsNotAnExceptionIsThrownIfTheThrowOptionIsSet()
    {
        new SearchstringParser('Hello NOT', array('throw' => true));
    }

    /**
     * @test
     */
    public function ifTheLastTermIsNotItIsDroppedSilentlyIfTheThrowOptionIsNotSet()
    {
        $search = new SearchstringParser('Test not');
        $this->assertSame(array('Test'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof NotAsLastTermException);
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\OrAsFirstOrLastTermException
     * @expectedExceptionMessage OR cannot be used as first or last term
     */
    public function ifTheFirstTermIsOrAnExceptionIsThrownIfTheThrowOptionIsSet()
    {
        new SearchstringParser('OR Hello', array('throw' => true));
    }

    /**
     * @test
     */
    public function ifTheFirstTermIsOrItIsDroppedSilentlyIfTheThrowOptionIsNotSet()
    {
        $search = new SearchstringParser('or Test');
        $this->assertSame(array('Test'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof OrAsFirstOrLastTermException);
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\OrAsFirstOrLastTermException
     */
    public function ifTheLastTermIsOrAnExceptionIsThrownIfTheThrowOptionIsSet()
    {
        new SearchstringParser('Hello OR', array('throw' => true));
    }

    /**
     * @test
     */
    public function ifTheLastTermIsOrItIsDroppedSilentlyIfTheThrowOptionIsNotSet()
    {
        $search = new SearchstringParser('Test or');
        $this->assertSame(array('Test'), $search->getAndTerms());
        $this->assertSame(array(), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof OrAsFirstOrLastTermException);
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\OrWithNegationException
     * @expectedExceptionMessage Cannot use OR with a term that is negated
     */
    public function ifANegatedTermPrecedesAnOrAnExceptionIsThrownIfTheThrowOptionIsSet()
    {
        new SearchstringParser('-Hello OR World', array('throw' => true));
    }

    /**
     * @test
     */
    public function ifANegatedTermPrecedesAnOrTheOrIsDroppedSilentlyIfTheThrowOptionIsNotSet()
    {
        $search = new SearchstringParser('-Hello OR World');
        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('World'), $search->getOrTerms());
        $this->assertSame(array('Hello'), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof OrWithNegationException);
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\OrWithNegationException
     */
    public function ifANegatedTermFollowsAnOrAnExceptionIsThrownIfTheThrowOptionIsSet()
    {
        new SearchstringParser('Hello OR -negated', array('throw' => true));
    }

    /**
     * @test
     */
    public function ifANegatedTermFollowsAnOrTheOrIsDroppedSilentlyIfTheThrowOptionIsNotSet()
    {
        $search = new SearchstringParser('Test or -negated');
        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('Test'), $search->getOrTerms());
        $this->assertSame(array('negated'), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof OrWithNegationException);
    }
}
