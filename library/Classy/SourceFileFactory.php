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
 * SourceFileFactory
 *
 * Handles the instantiation of all \Classy\SourceFile objects
 */
class SourceFileFactory {
	public $proxyDir;

	/**
	 * Constructor
	 * 
	 * @param string $proxyDir - directory to use for proxy files
	 * @access public
	 * @return void
	 */
	public function __construct($proxyDir) {
		$this->proxyDir = $proxyDir;
	}

	/**
	 * Returns a new \Classy\SourceFile for the given $file 
	 * 
	 * @param string $file - file to create an instance for
	 * @access public
	 * @return \Classy\SourceFile
	 */
	public function create($file) {
		return new \Classy\SourceFile($file, $this->proxyDir);
	}
}
