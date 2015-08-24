<?php

namespace BlueM;

use BlueM\SearchstringParser\ContradictoryModifiersException;
use BlueM\SearchstringParser\NotAsLastTermException;
use BlueM\SearchstringParser\ModifierAsFirstOrLastTermException;
use BlueM\SearchstringParser\UnclosedQuoteException;

require __DIR__ . '/../lib/BlueM/SearchstringParser.php';
require __DIR__ . '/../lib/BlueM/SearchstringParser/InvalidSyntaxException.php';
require __DIR__ . '/../lib/BlueM/SearchstringParser/ContradictoryModifiersException.php';
require __DIR__ . '/../lib/BlueM/SearchstringParser/UnclosedQuoteException.php';
require __DIR__ . '/../lib/BlueM/SearchstringParser/NotAsLastTermException.php';
require __DIR__ . '/../lib/BlueM/SearchstringParser/ModifierAsFirstOrLastTermException.php';

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
    public function The_AND_terms_are_returned_by_the_getter()
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
    public function The_OR_terms_are_returned_by_the_getter()
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
    public function The_NOT_terms_are_returned_by_the_getter()
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
    public function allTermsInASearchStringWithoutModifiersAreRegardedAsOptionalByDefault()
    {
        $search = new SearchstringParser('Hello World');

        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('Hello', 'World'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function All_terms_in_a_search_string_without_modifiers_are_regarded_as_mandatory_if_the_default_operator_is_set_to_AND()
    {
        $search = new SearchstringParser('Hello World',
            array('defaultOperator' => SearchstringParser::SYMBOL_AND)
        );

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

        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('Hello'), $search->getOrTerms());
        $this->assertSame(array('World'), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function A_NOT_excludes_the_following_term()
    {
        $search = new SearchstringParser('Hello NOT World');

        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('Hello'), $search->getOrTerms());
        $this->assertSame(array('World'), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function aPlusPrefixMakesTheFollowingTermRequires()
    {
        $search = new SearchstringParser('Hello +World');

        $this->assertSame(array('World'), $search->getAndTerms());
        $this->assertSame(array('Hello'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function An_AND_makes_the_previous_and_next_term_required()
    {
        $search = new SearchstringParser('Hello AND World AnotherString');

        $this->assertSame(array('Hello', 'World'), $search->getAndTerms());
        $this->assertSame(array('AnotherString'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function An_OR_makes_the_previous_and_next_term_optional()
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

        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('test', 'Hello World'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function escapedQuotesInsidePhrasesCanBeUsed()
    {
        $search = new SearchstringParser('"Hello \" World"');

        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('Hello " World'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function phrasesCanBeExcludedByPrefixingThemWithAMinus()
    {
        $search = new SearchstringParser('test -"Hello World"');

        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('test'), $search->getOrTerms());
        $this->assertSame(array('Hello World'), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function aTermWhichIsShorterThanTheDefinedMinimumIsSkipped()
    {
        $search = new SearchstringParser('AB C');

        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('AB'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array('C'), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function aCombinationOfSupportedSyntaxesIsParsedCorrectly()
    {
        $search = new SearchstringParser(
            '"search string parser" "PHP 5.4" OR "PHP 5.3" NOT "PHP 4" +OOP NOT C# -C++ C'
        );

        $this->assertSame(array('OOP'), $search->getAndTerms());
        $this->assertSame(array('search string parser', 'PHP 5.4', 'PHP 5.3'), $search->getOrTerms());
        $this->assertSame(array('PHP 4', 'C#', 'C++'), $search->getNotTerms());
        $this->assertSame(array('C'), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function aCombinationOfSupportedSyntaxesIsParsedCorrectlyWithAndAsDefaultOperator()
    {
        $search = new SearchstringParser(
            '"search string parser" "PHP 5.4" OR "PHP 5.3" NOT "PHP 4" +OOP NOT C# -C++ C',
            array('defaultOperator' => SearchstringParser::SYMBOL_AND)
        );

        $this->assertSame(array('search string parser', 'OOP'), $search->getAndTerms());
        $this->assertSame(array('PHP 5.4', 'PHP 5.3'), $search->getOrTerms());
        $this->assertSame(array('PHP 4', 'C#', 'C++'), $search->getNotTerms());
        $this->assertSame(array('C'), $search->getSkippedTerms());
    }

    /**
     * @test
     */
    public function aCombinationOfSupportedSyntaxesUsingAndIsParsedCorrectly()
    {
        $search = new SearchstringParser(
            'Word1 AND Word2 +"Phrase 1" -"Phrase 2" Word3 -Word4 Word5 OR Word6 OR Word7 NOT Word8 X'
        );

        $this->assertSame(array('Word1', 'Word2', 'Phrase 1'), $search->getAndTerms());
        $this->assertSame(array('Word3', 'Word5', 'Word6', 'Word7'), $search->getOrTerms());
        $this->assertSame(array('Phrase 2', 'Word4', 'Word8'), $search->getNotTerms());
        $this->assertSame(array('X'), $search->getSkippedTerms());
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
        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('Test', 'Hello World'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof UnclosedQuoteException);
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\NotAsLastTermException
     * @expectedExceptionMessage must not end with “NOT”
     */
    public function If_the_last_term_is_NOT_an_exception_is_thrown_if_the_throw_option_is_set()
    {
        new SearchstringParser('Hello NOT', array('throw' => true));
    }

    /**
     * @test
     */
    public function If_the_last_term_is_NOT_it_is_dropped_silently_if_the_throw_option_is_not_set()
    {
        $search = new SearchstringParser('Test not');
        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('Test'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof NotAsLastTermException);
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\ModifierAsFirstOrLastTermException
     * @expectedExceptionMessage must neither start nor end with “AND” or “OR”
     */
    public function If_the_first_term_is_AND_an_exception_is_thrown_if_the_throw_option_is_set()
    {
        new SearchstringParser('AND Hello', array('throw' => true));
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\ModifierAsFirstOrLastTermException
     * @expectedExceptionMessage must neither start nor end with “AND” or “OR”
     */
    public function If_the_first_term_is_OR_an_exception_is_thrown_if_the_throw_option_is_set()
    {
        new SearchstringParser('OR Hello', array('throw' => true));
    }

    /**
     * @test
     */
    public function If_the_first_term_is_OR_it_is_dropped_silently_if_the_throw_option_is_not_set()
    {
        $search = new SearchstringParser('or Test');
        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('Test'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof ModifierAsFirstOrLastTermException);
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\ModifierAsFirstOrLastTermException
     */
    public function If_the_last_term_is_AND_an_exception_is_thrown_if_the_throw_option_is_set()
    {
        new SearchstringParser('Hello AND', array('throw' => true));
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\ModifierAsFirstOrLastTermException
     */
    public function If_the_last_term_is_OR_an_exception_is_thrown_if_the_throw_option_is_set()
    {
        new SearchstringParser('Hello OR', array('throw' => true));
    }

    /**
     * @test
     */
    public function If_the_last_term_is_OR_it_is_dropped_silently_if_the_throw_option_is_not_set()
    {
        $search = new SearchstringParser('Test or');
        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('Test'), $search->getOrTerms());
        $this->assertSame(array(), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof ModifierAsFirstOrLastTermException);
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\ContradictoryModifiersException
     * @expectedExceptionMessage contradictory instructions
     */
    public function If_a_negated_term_precedes_an_OR_an_exception_is_thrown_if_the_throw_option_is_set()
    {
        new SearchstringParser('-Hello OR World', array('throw' => true));
    }

    /**
     * @test
     */
    public function If_a_negated_term_precedes_an_OR_the_OR_is_dropped_silently_if_the_throw_option_is_not_set()
    {
        $search = new SearchstringParser('-Hello OR World');
        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('World'), $search->getOrTerms());
        $this->assertSame(array('Hello'), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof ContradictoryModifiersException);
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\ContradictoryModifiersException
     * @expectedExceptionMessage contradictory instructions
     */
    public function If_a_required_term_is_preceded_by_NOT_an_exception_is_thrown_if_the_throw_option_is_set(
    )
    {
        new SearchstringParser('Word1 NOT +Word', array('throw' => true));
    }

    /**
     * @test
     * @expectedException \BlueM\SearchstringParser\ContradictoryModifiersException
     */
    public function If_a_negated_term_follows_an_OR_an_exception_is_thrown_if_the_throw_option_is_set()
    {
        new SearchstringParser('Hello OR -negated', array('throw' => true));
    }

    /**
     * @test
     */
    public function If_a_negated_term_follows_an_OR_the_OR_is_dropped_silently_if_the_throw_option_is_not_set()
    {
        $search = new SearchstringParser('Test or -negated');
        $this->assertSame(array(), $search->getAndTerms());
        $this->assertSame(array('Test'), $search->getOrTerms());
        $this->assertSame(array('negated'), $search->getNotTerms());
        $this->assertSame(array(), $search->getSkippedTerms());

        $exceptions = $search->getExceptions();
        $this->assertTrue($exceptions[0] instanceof ContradictoryModifiersException);
    }
}
