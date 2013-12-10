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
