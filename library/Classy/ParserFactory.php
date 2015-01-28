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
 * ParserFactory
 *
 * Handles the instantiation of all \Classy\Parser objects
 */
class ParserFactory {
	private $cache;

	/**
	 * Constructor 
	 * 
	 * @param \Classy\Cache $cache - cache object to use when creating parsers
	 * @access public
	 * @return void
	 */
	public function __construct(\Classy\Cache $cache) {
		$this->cache = $cache;
	}

	/**
	 * Returns a new Parser instance for the given sourceFile
	 * 
	 * @param \Classy\SourceFile $sourceFile 
	 * @access public
	 * @return \Classy\Parser
	 */
	public function create(\Classy\SourceFile $sourceFile) {
		return  new \Classy\Parser($sourceFile, $this->cache);
	}
}
