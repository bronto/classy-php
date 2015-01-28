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
