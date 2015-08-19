<?php

namespace BlueM;

use BlueM\SearchstringParser\NotAsLastTermException;
use BlueM\SearchstringParser\OrWithNegationException;
use BlueM\SearchstringParser\UnclosedQuoteException;
use BlueM\SearchstringParser\OrAsFirstOrLastTermException;

/**
 * Takes a search-engine style search string and breaks it up, recognizing
 * quoted strings, "+" and "-" prefixes as well as "NOT" and "OR".
 *
 * @author  Carsten Bluem <carsten@bluem.net>
 * @license http://www.opensource.org/licenses/bsd-license.php BSD 2-Clause License
 */
class SearchstringParser
{
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
     *                        have; default value: 2) / "throw" (bool: throw an exception
     *                        in case of parsing error?; default: false)
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

        $this->options['throw'] = !empty($options['throw']);
        $this->parseSearchString($string);
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
     * @param string $string The search string
     */
    protected function parseSearchString($string)
    {
        $string = trim($string);
        $params = array();

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
            // like a boolean modifier (-) and decrement $qstart
            if ($preceding === '-') {
                $phrase = $preceding . $phrase;
                $qstart --;
            }

            // Get unquoted strings before the quotes start
            if ($qstart > 0) {
                $params = array_merge(
                    $params,
                    $this->splitAtWhitespace(substr($string, 0, $qstart))
                );
            }

            $params[] = $phrase;
            $string   = substr($string, $qend + 1);
        }

        // Process the remaining string
        if (trim($string)) {
            $params = array_merge($params, $this->splitAtWhitespace($string));
        }

        $this->categorizeTerms($params);

        if ($this->options['throw'] && count($this->exceptions)) {
            throw $this->exceptions[0];
        }
    }

    /**
     * @param string $string
     *
     * @return array
     */
    protected function splitAtWhitespace($string)
    {
        return preg_split('#\s+#', trim($string));
    }

    /**
     * @param array $terms
     */
    protected function categorizeTerms(array $terms)
    {
        $categorized = array();

        for ($i = 0, $ii = count($terms); $i < $ii; $i ++) {

            if ('-' === substr($terms[$i], 0, 1)) {
                $categorized[] = array('-', substr($terms[$i], 1));
                continue;
            }

            if ('not' === strtolower($terms[$i])) {
                if ($i === $ii - 1) {
                    $this->exceptions[] = new NotAsLastTermException();
                    break;
                }
                $i ++;
                $categorized[] = array('-', $terms[$i]);
                continue;
            }

            $categorized[] = array('+', $terms[$i]);
        }

        // Find "OR" pairs
        for ($i = 0, $ii = count($categorized); $i < $ii; $i ++) {

            if ('or' !== strtolower($categorized[$i][1])) {
                continue; // Not interested in this term
            }

            unset($categorized[$i]);

            if ($i === 0) {
                $this->exceptions[] = new OrAsFirstOrLastTermException();
                continue;
            }

            if ($i === $ii - 1) {
                $this->exceptions[] = new OrAsFirstOrLastTermException();
                break;
            }

            if ('|' !== $categorized[$i - 1][0]) {
                // Previous term not "OR"ed
                if ('-' === $categorized[$i - 1][0]) {
                    $this->exceptions[] = new OrWithNegationException();
                } else {
                    $categorized[$i - 1][0] = '|';
                }
            }

            $i ++;

            if ('-' === $categorized[$i][0]) {
                $this->exceptions[] = new OrWithNegationException();
            } else {
                $categorized[$i][0] = '|';
            }
        }

        foreach ($categorized as $term) {
            if (!$term[1]) {
                continue;
            }
            if (mb_strlen($term[1]) < $this->options['minlength']) {
                $this->skipped[] = $term[1];
            } elseif ('+' === $term[0]) {
                $this->andTerms[] = $term[1];
            } elseif ('-' === $term[0]) {
                $this->notTerms[] = $term[1];
            } else {
                $this->orTerms[] = $term[1];
            }
        }
    }
}
