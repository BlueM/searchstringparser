[![SensioLabsInsight](https://insight.sensiolabs.com/projects/2dc0e9b6-2357-40bd-a56b-9a8dade3408f/mini.png)](https://insight.sensiolabs.com/projects/2dc0e9b6-2357-40bd-a56b-9a8dade3408f)
[![Build Status](https://travis-ci.org/BlueM/searchstringparser.svg?branch=master)](https://travis-ci.org/BlueM/searchstringparser)

SearchstringParser Overview
===========================

What is it?
--------------
SearchstringParser is a library for PHP 5.3 or higher which will take a “typical” search-engine-like search string and split it into parts. It supports phrases, required, optional and excluded terms/phrases by using `+`, `-`, `AND`, `NOT` and `OR`.

If you use a search engine like Apache Solr which does the parsing itself, you may not need a library such as this. But in cases where you need switchable search backends but still have a consistent search syntax or where you simply use your SQL database’s fulltext search features, it provides simple and easy parsing. Equally, even when using a software such as Solr, it can be handy to control what is passed to Solr, for instance to optimize the search syntax (example: by setting the `mm` parameter depending on the number of optional terms).


Installation
------------
The preferred way to install this library is through [Composer](https://getcomposer.org). For this, add `"bluem/searchstringparser": "~1.0"` to the requirements in your `composer.json` file. As this library uses [semantic versioning](http://semver.org), you will get fixes and feature additions when running `composer update`, but not changes which break the API.

Alternatively, you can clone the repository using git or download a tagged release.


Supported Syntax
----------------

* A term which is prefixed by `+` is regarded as required. Example: `+word`.
* When there is an `AND` between two terms, both are regarded as required. Example: `Hello AND World`.
* A term which is prefixed by `-` is regarded as excluded. Example: `-word`
* A term which is preceded by `NOT` is regarded as excluded. Example: `NOT word`
* When there is an `OR` between two terms, both are regarded as optional. Example: `Hello OR World`.
* Phrases can be specified using double quotes. Double quotes inside a phrase can be escaped using a backslash. Example: `"my \" phrase"`.
* Everything said above regarding `+`, `-`, `AND`, `OR`, `NOT` applies to phrases as well.

Any term to which none of above rules applies, is by default regarded as an optional term . This can be changed by passing `array('defaultOperator' => SearchstringParser:SYMBOL_AND)` as argument 2 to `SearchstringParser`’s constructor to make such terms required.

Examples:

* `Hello World` ➔ Optional terms “Hello” and “World”, no required or excluded terms
* `Hello World -foobar` ➜ Optional terms “Hello” and “World”, excluded term “foobar”, no required terms (Equivalent to: `Hello World NOT foobar`)
* `+"search string parser" "PHP 5.6" OR "PHP 5.3" NOT "PHP 4" NOT C# -C++ C` ➔ Required phrase “search string parser”, optional phrases “PHP 5.6” and “PHP 5.3”, excluded phrases/terms “PHP 4”, “C#” and “C++” and skipped term “C” (which is shorter than the default minimum length of 2 characters)

Example with `array('defaultOperator' => SearchstringParser:SYMBOL_AND)`:
* `Hello World -foobar` ➜ Required terms “Hello” and “World”, excluded term “foobar”, no optional terms


Usage
========
```php
$search = new BlueM\SearchstringParser('Your AND string long OR short NOT "exclude this phrase" X');

$search->getAndTerms();     // array('your', 'string')
$search->getOrTerms();      // array('long', 'short')
$search->getNotTerms();     // array('exclude this phrase')
$search->getSkippedTerms(); // array('X')
```

Changing the minimum length
---------------------------
Simply pass the length to the constructor:

```php
$search = new BlueM\SearchstringParser('...', array('minlength' => 3));
```


Dealing with errors
---------------------------
The following errors might occur:

* A phrase is opened using `"`, but not closed
* “NOT” is used as last term
* “OR” is used as first or last term
* “AND” is used as first or last term
* “OR” is preceded or followed by an excluded term/phrase
* “AND” is preceded or followed by an excluded term/phrase
* “NOT” is followed by a term prefixed with “+”

The default behaviour is to not throw exceptions, but to make the best out of the situation. (See unit tests or Testdox output for details.) SearchstringParser will still collect exceptions, so if you want to provide hints to the user, you can do that by getting them via method `getExceptions()`. As `SearchstringParser` throws different exceptions depending on the type of problem, you can nicely handle (or ignore) the errors separately, for example by performing `instanceof` checks.


Author & License
====================
This code was written by Carsten Blüm ([www.bluem.net](http://www.bluem.net)) and licensed under the BSD 2-Clause license.


Changes from earlier versions
=============================

From 2.0.1 to 2.0.2
-----------------
* Use PSR-4 instead of PSR-0

From 2.0 to 2.0.1
-----------------
* HHVM compatibility

From 1.0.1 to 2.0
-----------------
* API is unchanged, but the semantics have changed. Versions below 2 behaved as if `defaultOperator` (introduced with 2.0) was set to `SearchstringParser:SYMBOL_AND`
