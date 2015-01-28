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
 * IniHelper
 *
 * This class provides a simple wrapper around the ini_get/ini_set system functions so unit tests can mock their behavior.
 */
class IniHelper {
	/**
	 * Returns the results of ini_get($name)
	 * 
	 * @param string $name - name of key to poll
	 * @access public
	 * @return string
	 */
	public function get($name) {
		return ini_get($name);
	}

	/**
	 * Calls ini_set($name, $value). 
	 * 
	 * @param string $name - name of key to set
	 * @param mixed $value - value to set for $name
	 * @access public
	 * @return void
	 */
	public function set($name, $value) {
		return ini_set($name, $value);
	}
}
