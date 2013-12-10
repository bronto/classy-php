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
 * Cache
 *
 * Parsing source classes is fairly expensive so this class provides a simple file-based cache containing the locations
 * of the classifized versions. 
 */
class Cache {
	const CACHE_FILE = 'cache.classy';

	private $data;
	private $cacheDir;
	private $persistance;

	/**
	 * Constructor
	 * 
	 * @param string $cacheDir - directory to store cache file in
	 * @access public
	 * @return void
	 */
	public function __construct($cacheDir) {
		$this->cacheDir = $cacheDir;
	}

	public function __destruct() {
		if (!$this->persistance) {
			return;
		}

		file_put_contents($this->getCacheFilename(), $this->export());
	}

	/**
	 * Adds the $proxyFile to the given $sourceFile in the cache.  Overwrites any existing data for $sourceFile
	 * if already present in the cache.
	 * 
	 * @param \Classy\SourceFile $sourceFile - SourceFile to key the cache entry on
	 * @access public
	 * @return void
	 */
	public function add(\Classy\SourceFile $sourceFile) {
		$file = $sourceFile->getFile();
		$this->data[$file] = array (
			'mtime' => $sourceFile->getMtime(),
		);
	}

	/**
	 * Returns true if the given $sourceFile is in the cache AND is older (or as old) as the classifized version. If
	 * the $sourceFile has been modified since it was cached, we will consider it to be not in the cache.
	 * 
	 * @param \Classy\SourceFile $sourceFile - SourceFile to key the cache entry on
	 * @access public
	 * @return boolean
	 */
	public function contains(\Classy\SourceFile $sourceFile) {
		$file = $sourceFile->getFile();
		return $this->data[$file]['mtime'] >= $sourceFile->getMtime();
	} 

	/**
	 * Turns on persistance.  If enabled, the cache will automatically write to disk on __destruct().
	 * 
	 * @access public
	 * @return void
	 */
	public function enablePersistance() {
		$this->persistance = true;
	}

	/**
	 * Returns an importable string version of this cache.
	 * 
	 * @access public
	 * @return string
	 */
	public function export() {
		return serialize($this->data);
	}

	private function getCacheFilename() {
		return $this->cacheDir . DIRECTORY_SEPARATOR . self::CACHE_FILE;
	}

	/**
	 * Reads in the cache from disk and loads into memory. 
	 * 
	 * @access public
	 * @return void
	 */
	public function import() {
		$file = $this->getCacheFilename();
		if (!file_exists($file)) {
			return;
		}

		$contents = file_get_contents($file);
		$this->data = unserialize($contents);
	}
}
