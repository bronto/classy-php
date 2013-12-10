<?php
/**
 * PHP version 5
 *
 * Copyright (c) 2013 Bronto Software Inc.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS
 * OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @package     Classy
 * @author      Ed Dawley <ed@eddawley.com>
 * @copyright   2013 Bronto Software Inc.
 * @license     http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link        https://github.com/bronto/classy-php
 */

namespace Classy;

/**
 * Parser 
 * 
 * This class manages the parsing of the original source of a class including sending the new version to both disk and the cache.
 */
class Parser {
	private $cache;
	private $sourceFile;

	private static $classifizer;
	private static $parser;
	private static $printer;
	private static $traverser;

	/**
	 * Constructor 
	 * 
	 * @param \Classy\SourceFile $sourceFile - object representing the original source
	 * @param \Classy\Cache $cache - instance of current cache
	 * @access public
	 * @return void
	 */
	public function __construct(\Classy\SourceFile $sourceFile, \Classy\Cache $cache) {
		$this->cache = $cache;
		$this->sourceFile = $sourceFile;
	}

	/**
	 * Helper function to initialize static members.  Most of these variables are static solely for performance reasons since they
	 * can be reused across all classes being parsed.
	 * 
	 * @static
	 * @access public
	 * @return void
	 */
	public static function initStatic() {
		self::$parser = new \PHPParser_Parser(new \PHPParser_Lexer);
		self::$printer = new \PHPParser_PrettyPrinter_Default();

		self::$classifizer = new \Classy\NodeVisitor\Classifizer;

		self::$traverser = new \PHPParser_NodeTraverser;

		// this will make all namespaced elements fully qualified
		self::$traverser->addVisitor(new \PHPParser_NodeVisitor_NameResolver);

		// this performs all other code substitutions
		self::$traverser->addVisitor(self::$classifizer);
	}

	/**
	 * Main entry for parsing a sourceFile.  This function ensures the new version is created, loaded, and cached. 
	 * 
	 * @access public
	 * @return void
	 */
	public function parse() {
		if ($this->cache->contains($this->sourceFile)) {
			$this->sourceFile->loadProxy();
			return; 
		}

		self::$classifizer->setSourceFile($this->sourceFile);

		// create the empty replacement class file.  This is so that any hardcoded include/requires
		// don't inadvertantly try to load the original source (and fail with duplicate class)
		$this->sourceFile->writeEmpty();

		$source = $this->sourceFile->getContents();

		// PHP_Parser has to do some serious recursion for complex code so we need to ensure it won't hit the nesting limit
		$originalNestingMax = ini_get('xdebug.max_nesting_level');
		ini_set('xdebug.max_nesting_level', 2000);

		$nodes = self::$parser->parse($source);
		$nodes = self::$traverser->traverse($nodes);

		$contents = "<?php\n" . self::$printer->prettyPrint($nodes);

		ini_set('xdebug.max_nesting_level', $originalNestingMax);

		$this->sourceFile->writeProxy($contents);
		$this->cache->add($this->sourceFile);
		$this->sourceFile->loadProxy();
	}
}

Parser::initStatic();
