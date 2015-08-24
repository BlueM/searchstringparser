<?php

namespace BlueM;

use BlueM\SearchstringParser\ContradictoryModifiersException;
use BlueM\SearchstringParser\NotAsLastTermException;
use BlueM\SearchstringParser\UnclosedQuoteException;
use BlueM\SearchstringParser\ModifierAsFirstOrLastTermException;

/**
 * Takes a search-engine style search string and breaks it up, recognizing
 * quoted strings, "+" and "-" prefixes as well as "NOT" and "OR".
 *
 * @author  Carsten Bluem <carsten@bluem.net>
 * @license http://www.opensource.org/licenses/bsd-license.php BSD 2-Clause License
 */
class SearchstringParser
{
    const SYMBOL_AND = '+';
    const SYMBOL_OR  = '|';
    const SYMBOL_NOT = '-';

    /**
     * @var array
     */
    protected $andTerms = array();

    /**
     * @var array
     */
    protected $orTerms = array();

    /**
     * @var array
     */
    protected $notTerms = array();

    /**
     * @var array
     */
    protected $skipped = array();

    /**
     * @var array
     */
    protected $exceptions = array();

    /**
     * @var array
     */
    protected $options = array(
        'minlength' => 2,
        'throw'     => false,
    );

    /**
     * Constructor. Takes a string and immediately parses it.
     *
     * @param string $string  String to be parsed
     * @param array  $options Associative array with 0 or more of keys of: "minlength"
     *                        (minimum length in characters a search term or phrase must
     *                        have; default value: 2), "throw" (bool: throw an exception
     *                        in case of parsing error?; default: false) or
     *                        "defaultOperator" (set to SearchstringParser:SYMBOL_AND
     *                        if you want unclassified terms to be regarded as required.
     *                        Default is SearchstringParser:SYMBOL_OR).
     *
     * @throws \InvalidArgumentException
     *
     */
    public function __construct($string, array $options = array())
    {
        if (!trim($string)) {
            throw new \InvalidArgumentException('Empty search string');
        }

        if (array_key_exists('minlength', $options)) {
            if (!preg_match('/^[1-9]\d*$/', trim($options['minlength']))) {
                throw new \InvalidArgumentException('Invalid minimum length');
            }
            $this->options['minlength'] = (int) $options['minlength'];
        }

        if (array_key_exists('defaultOperator', $options)) {
            if (self::SYMBOL_AND === $options['defaultOperator'] ||
                self::SYMBOL_OR === $options['defaultOperator'])
            {
                $defaultOperator = $options['defaultOperator'];
            } else {
                throw new \InvalidArgumentException('Invalid default operator');
            }
        } else {
            $defaultOperator = self::SYMBOL_OR;
        }

        $this->options['throw'] = !empty($options['throw']);

        $terms = $this->parseToTerms($string);
        $terms = $this->processModifierTerms($terms);

        $this->categorizeTerms($terms, $defaultOperator);

        if ($this->options['throw'] && count($this->exceptions)) {
            throw $this->exceptions[0];
        }
    }

    /**
     * @return array
     */
    public function getAndTerms()
    {
        return $this->andTerms;
    }

    /**
     * @return array
     */
    public function getOrTerms()
    {
        return $this->orTerms;
    }

    /**
     * @return array
     */
    public function getNotTerms()
    {
        return $this->notTerms;
    }

    /**
     * @return array
     */
    public function getSkippedTerms()
    {
        return $this->skipped;
    }

    /**
     * @return array
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * Splits the search string into "terms", which can be words or quoted phrases
     *
     * @param string $string The search string
     *
     * @return array
     */
    protected function parseToTerms($string)
    {
        $string = trim($string);
        $terms  = array();

        // Find quoted strings and remove them from $string
        while (false !== $qstart = strpos($string, '"')) {
            // Try to find closing quotation mark, starting at $qstart + 1
            $offset = $qstart + 1;
            $qend   = 0;

            while (!$qend) {
                if (false === $i = strpos($string, '"', $offset)) {
                    // No closing quotation mark before end of string >> use rest
                    $this->exceptions[] = new UnclosedQuoteException(
                        'Opening quote not closed before end of string'
                    );
                    $qend = strlen($string);
                } else {
                    // Make sure this quotation mark is not escaped
                    if ('\\' !== substr($string, $i - 1, 1)) {
                        $qend = $i; // Not escaped >> set $qend in order to break loop
                    } else {
                        $offset ++; // Escaped >> proceed with next character
                    }
                }
            }

            $phrase = str_replace('\\"', '"', substr($string, $qstart + 1, $qend - $qstart - 1));

            // Get the character preceding the opening quote character
            $preceding = $qstart > 0 ? substr($string, $qstart - 1, 1) : false;

            // Prepend $phrase with $preceding if $preceding looks
            // like a modifier symbol and decrement $qstart
            if (static::SYMBOL_AND === $preceding ||
                static::SYMBOL_NOT === $preceding
            ) {
                $symbol = $preceding;
                $qstart --;
            } else {
                $symbol = null;
            }

            // Get unquoted strings before the quotes start
            if ($qstart > 0) {
                foreach ($this->processNonPhraseTerms(substr($string, 0, $qstart)) as $term) {
                    $terms[] = $term;
                }
            }

            $terms[] = array($symbol, $phrase);
            $string  = substr($string, $qend + 1);
        }

        // Process the remaining string
        if (trim($string)) {
            foreach ($this->processNonPhraseTerms($string) as $term) {
                $terms[] = $term;
            }
        }

        return $terms;
    }

    /**
     * @param array $terms
     *
     * @return array
     */
    private function processModifierTerms(array $terms)
    {
        for ($i = 0, $ii = count($terms); $i < $ii; $i++) {

            switch (strtolower($terms[$i][1])) {
                case 'not':
                    if (empty($terms[$i + 1][1])) {
                        $this->exceptions[] = new NotAsLastTermException();
                    } else {
                        if (empty($terms[$i + 1][0]) || self::SYMBOL_NOT === $terms[$i + 1][0]) {
                            $terms[$i + 1][0] = self::SYMBOL_NOT;
                        } else {
                            $this->exceptions[] = new ContradictoryModifiersException();
                        }
                    }
                    unset($terms[$i]);
                    continue 2;
                case 'or':
                    $symbol = static::SYMBOL_OR;
                    break;
                case 'and':
                    $symbol = static::SYMBOL_AND;
                    break;
                default:
                    // Not interested in this
                    continue 2;
            }

            unset($terms[$i]);

            if ($i === 0) {
                $this->exceptions[] = new ModifierAsFirstOrLastTermException();
                continue;
            }

            if ($i === $ii - 1) {
                $this->exceptions[] = new ModifierAsFirstOrLastTermException();
                break;
            }

            if ($symbol !== $terms[$i - 1][0]) {
                // Previous term does not have same modifier
                if (self::SYMBOL_NOT === $terms[$i - 1][0]) {
                    $this->exceptions[] = new ContradictoryModifiersException();
                } else {
                    $terms[$i - 1][0] = $symbol;
                }
            }

            $i ++;

            if (static::SYMBOL_NOT === $terms[$i][0]) {
                $this->exceptions[] = new ContradictoryModifiersException();
            } else {
                $terms[$i][0] = $symbol;
            }
        }

        return $terms;
    }

    /**
     * @param array $terms
     */
    protected function categorizeTerms(array $terms, $defaultOperator)
    {
        foreach ($terms as $term) {
            if (mb_strlen($term[1]) < $this->options['minlength']) {
                $this->skipped[] = $term[1];
            } elseif (static::SYMBOL_AND === $term[0]) {
                $this->andTerms[] = $term[1];
            } elseif (static::SYMBOL_NOT === $term[0]) {
                $this->notTerms[] = $term[1];
            } elseif (static::SYMBOL_OR === $term[0]) {
                $this->orTerms[] = $term[1];
            } else {
                if (self::SYMBOL_AND === $defaultOperator) {
                    $this->andTerms[] = $term[1];
                } else {
                    $this->orTerms[] = $term[1];
                }
            }
        }
    }

    /**
     * @param string $string
     *
     * @return array
     */
    private function processNonPhraseTerms($string)
    {
        $terms = array();

        $trimmedString = trim($string);

        if (!$trimmedString) {
            return array();
        }

        foreach (preg_split('#\s+#', $trimmedString) as $term) {

            if (self::SYMBOL_AND === substr($term, 0, 1)) {
                $terms[] = array(self::SYMBOL_AND, substr($term, 1));
                continue;
            } elseif (self::SYMBOL_NOT === substr($term, 0, 1)) {
                $terms[] = array(self::SYMBOL_NOT, substr($term, 1));
            } else {
                $terms[] = array(null, $term);
            }

        }

        return $terms;
    }
}
