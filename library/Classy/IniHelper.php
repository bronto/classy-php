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
