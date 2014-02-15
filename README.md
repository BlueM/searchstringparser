SearchstringParser Overview
=========================

What is it?
--------------
SearchstringParser is a class for PHP 5.3 or higher which will take a “typical” search-engine like search string and split it into parts. It supports phrases and optional or excluded terms/phrases by using “-” and “NOT” and “OR”.

If you use a search engine like Solr which does the parsing itself, you will probably not need a library such as this. But in cases where you need switchable search backends but still have a consistent search syntax or where you simply use your SQL database’s fulltext search features, it provides simple and easy parsing.


Supported Syntax
----------------

`Hello World` ➔ Required strings “Hello” and “World”

`Hello World -excluded` ➜ Required strings “Hello” and “World” and excluded string “excluded”. (Equivalent to: `Hello World NOT excluded`)

`"Hello World" -excluded` ➜ Required phrase “Hello World” and excluded string “excluded”

`"Hello World" -"Hello world example"` ➜ Required phrase “Hello World” and excluded string “excluded”. (Equivalent to: `"Hello World" NOT "Hello world example"`)

`"search string parser" "PHP 5.4" OR "PHP 5.3" NOT "PHP 4" NOT C# -C++ C` ➔ Required phrase “search string parser”, optional phrases “PHP 5.4” and “PHP 5.3”, excluded phrases/terms “PHP 4”, “C#” and “C++” and skipped term “C” (which is shorter than the default minimum lenght of 2 characters)

Usage
========

	$search = new SearchstringParser('Your string long OR short NOT "exclude this phrase" X');

	$search->getAndTerms();     // array('your', 'search', 'string')
	$search->getOrTerms();      // array('long', 'short')
	$search->getNotTerms();     // array('exclude this phrase')
	$search->getSkippedTerms(); // array('X')


Changing the minimum length
---------------------------
Simply pass the length to the constructor:
    $search = new SearchstringParser('...', array('minlength' => 8));


Dealing with parsing errors
---------------------------

The following parsing errors might occur:

* A phrase is opened using `"`, but not closed
* “NOT” is used as last term
* “OR” is used as first or last term
* “OR” is preceded or followed by an excluded term/phrase

The default behaviour is to not throw exceptions, but to make the best out of the situation. (See unit tests or Testdox output for details.) SearchstringParser will still collect exceptions, so if you want to provide hints to the user, you can do that by getting them via method `getSkippedTerms()`. As SearchstringParser throws different exceptions depending on the type of problem, you can nicely handle (or ignore) the errors separately, for example by performing `instanceof` checks.

If, on the other hand, you prefer to not accept invalid syntax, you can set option “throw” to true when instantiating the class:
    $search = new SearchstringParser('...', array('throw' => true));

