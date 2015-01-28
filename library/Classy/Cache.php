<?php
/**
 * PHP version 5
 *
 * Copyright 2015 Bronto Software, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package     Classy
 * @author      Ed Dawley <ed@eddawley.com>
 * @copyright   2015 Bronto Software Inc.
 * @license     http://www.apache.org/licenses/LICENSE-2.0.txt  Apache License, Version 2.0
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
