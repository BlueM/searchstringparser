<?php

namespace BlueM;

use BlueM\SearchstringParser\NotAsLastTermException;
use BlueM\SearchstringParser\OrWithNegationException;
use BlueM\SearchstringParser\UnclosedQuoteException;
use BlueM\SearchstringParser\OrAsFirstOrLastTermException;

/**
 * Takes a search-engine style search string and breaks it up, recognizing
 * quoted strings as well "-" prefix and "NOT" and "OR".
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
     * @param       $string
     * @param array $options Associative array with 0 or more of keys "minlength"
     *                       (minimum length in characters a search term or phrase must
     *                       have; default value: 2) and "throw" (bool: throw an exception
     *                       in case of parsing error?; default: false)
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
            if (strval(intval($options['minlength'])) != strval($options['minlength']) ||
                intval($options['minlength']) < 1) {
                throw new \InvalidArgumentException('Invalid minimum length');
            }
            $this->options['minlength'] = $options['minlength'];
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
                    if (substr($string, $i - 1, 1) != '\\') {
                        $qend = $i; // Not escaped >> set $qend in order to break loop
                    } else {
                        $offset++; // Escaped >> proceed with next character
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
                $qstart--;
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

        $this->classifyTerms($params);

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
    protected function classifyTerms(array $terms)
    {
        $classified = array();

        for ($i = 0, $ii = count($terms); $i < $ii; $i++) {

            if ('-' === substr($terms[$i], 0, 1)) {
                $classified[] = array('-', substr($terms[$i], 1));
                continue;
            }

            if ('not' === strtolower($terms[$i])) {
                if ($i === $ii - 1) {
                    $this->exceptions[] = new NotAsLastTermException('NOT cannot be used as last term');
                    break;
                }
                $i ++;
                $classified[] = array('-', $terms[$i]);
                continue;
            }

            $classified[] = array('+', $terms[$i]);
        }

        // Find "OR" pairs
        for ($i = 0, $ii = count($classified); $i < $ii; $i++) {

            if ('or' !== strtolower($classified[$i][1])) {
                continue; // Not interested in this term
            }

            unset($classified[$i]);

            if ($i === 0) {
                $this->exceptions[] = new OrAsFirstOrLastTermException(
                    'OR cannot be used as first or last term'
                );
                continue;
            }

            if ($i === $ii - 1) {
                $this->exceptions[] = new OrAsFirstOrLastTermException(
                    'OR cannot be used as first or last term'
                );
                break;
            }

            if ('|' === $classified[$i - 1][0]) {
                // Previous term already "OR"ed, nothing to do
            } elseif ('-' === $classified[$i - 1][0]) {
                $this->exceptions[] = new OrWithNegationException(
                    'Cannot use OR with a term that is negated using - or NOT'
                );
            } else {
                $classified[$i - 1][0] = '|';
            }

            $i ++;

            if ('-' === $classified[$i][0]) {
                $this->exceptions[] = new OrWithNegationException(
                    'Cannot use OR with a term that is negated using - or NOT'
                );
            } else {
                $classified[$i][0] = '|';
            }
        }

        foreach ($classified as $term) {
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
