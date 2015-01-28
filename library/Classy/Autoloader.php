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
 * Autoloader
 *
 * This class is used for handling autoloading of classy itself.  The loading is a simple PSR-0 compliant load.
 * Be aware that this is *not* used to load any client classes.
 */
class Autoloader{
	/**
	 * Registers Classy's internal autoloader onto the spl stack
	 * 
	 * @access public
	 * @return void
	 */
	public function register(){
		spl_autoload_register(array($this, 'loadClass'));
	}

	/**
	 * Removes Classy's internal autoloader from the spl stack 
	 * 
	 * @access public
	 * @return void
	 */
	public function unregister(){
		spl_autoload_unregister(array($this, 'loadClass'));
	}

	/**
	 * The meat of the autoloader.  This function maps classname to file and requires the source.
	 * 
	 * @param string $class - name of class to load
	 * @access public
	 * @return void
	 */
	public function loadClass($class){
		if (strpos($class, 'Classy') !== 0) {
			// short-circuit if not Classy
			return;
		}

		$file = strtr($class, array('_' => DIRECTORY_SEPARATOR, '\\' => DIRECTORY_SEPARATOR));
		require_once("$file.php");
	}
}
