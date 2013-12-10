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
 * SourceFile
 *
 * This class represents an instance of a client's original php source file. In addition to providing access to 
 * expected file attributes, this class is also responsible for writing classifized versions of the source to disk as 
 * well as actually running php on it.
 */
class SourceFile {
	private $file;
	private $proxyBaseDir;

	/**
	 * Constructor
	 * 
	 * @param string $file - original php file (with full path)
	 * @param string $proxyBaseDir - base directory to use when writing classifized versions
	 * @access public
	 * @return void
	 */
	public function __construct($file, $proxyBaseDir) {
		$this->file = $file;
		$this->proxyBaseDir = $proxyBaseDir;
	}

	/**
	 * Returns the contents of the original file from disk
	 * 
	 * @access public
	 * @return string
	 */
	public function getContents() {
		return file_get_contents($this->file);
	}

	/**
	 * Returns the directory of the original file 
	 * 
	 * @access public
	 * @return string
	 */
	public function getDir() {
		return dirname($this->file);
	}

	/**
	 * Returns the original file
	 * 
	 * @access public
	 * @return string
	 */
	public function getFile() {
		return $this->file;
	}

	/**
	 * Returns the mtime of the original file as reported by the filesystem
	 * 
	 * @access public
	 * @return int
	 */
	public function getMtime() {
		// note we don't call clearstatcache because we're ok with getting the same mtime every call.  Plus this code should really only
		// be called once per file per process
		$stat = stat($this->file);
		return $stat['mtime'];
	}

	/**
	 * Returns the file location (including full path) to use for the classifized version of the original
	 * 
	 * @access private
	 * @return string
	 */
	private function getProxyFileName() {
		$pathinfo = pathinfo($this->file);
		$dir = $pathinfo['dirname'];
		$fileNameNoExt = $pathinfo['filename'];

		return $this->proxyBaseDir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $fileNameNoExt . ".classy.php";
	}

	/**
	 * Causes PHP to evaluate the classifized version of the original
	 * 
	 * @access public
	 * @return void
	 */
	public function loadProxy() {
		require_once($this->getProxyFileName());
	}

	/**
	 * Creates a blank file in case external code directly requires/includes the file. Will create the directory
	 * and ancestors if necessary
	 * 
	 * @access public
	 * @return void
	 */
	public function writeEmpty() {
		$file = $this->proxyBaseDir . $this->file;
		$dir = dirname($file);
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}

		touch($file);
	}

	/**
	 * Writes the given $contents as the classifized version of the original. Will create the classifized directory
	 * and ancestors if necessary
	 * 
	 * @param string $contents - classifized version of the original file
	 * @access public
	 * @return void
	 */
	public function writeProxy($contents) {
		$proxy = $this->getProxyFileName();

		// ensure directory exists before writing the file
		$dir = dirname($proxy);
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}

		file_put_contents($proxy, $contents);
	}
}
