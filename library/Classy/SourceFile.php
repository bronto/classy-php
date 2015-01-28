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
