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
 * Interceptor
 *
 * This class attempts to intercept all client classes during autoload.  It accomplishes this by 
 * prepending its own autoloader to the spl stack as well as overriding the include_path in favor of classifized
 * versions of the client class.  The intercepting autoloader runs the client class through our own classifizer
 * and then performs a require_once on the resulting class file.
 * 
 * This class is also responsible for the life cycle of the cache.
 */
class Interceptor {
	private $classFilter;
	private $classLocator;
	private $cache;
	private $cacheDir;
	private $classyIncludePath;
	private $originalIncludePath;

	/**
	 * Constructor
	 * 
	 * @param string $cacheDir - directory to use for all caching and classifized files
	 * @param \Closure $classLocator - function to use to map class name to file location
	 * @access public
	 * @return void
	 */
	public function __construct($cacheDir, \Closure $classLocator) {
		$this->cacheDir = $cacheDir;
		$this->classLocator = $classLocator;
	}

	/**
	 * The actual intercepting autoloader
	 *
	 * This function uses the registered $classLoader to get the real class and then classifizes it.  Any classes
	 * for whom the $classFilter does not return true will be ommitted.  In addition, we temporarily override the 
	 * include_path with classy locations so we can handle many direct includes.
	 *
	 * Note that this function is called for *every* client class including all libraries.  This means it should be as performant
	 * as possible in order to minimize additional overhead when running unit tests.
	 * 
	 * @param mixed $class 
	 * @access private
	 * @return void
	 */
	private function autoload($class) {
		// always avoid ourselves and our dependancies
		if (preg_match('/Classy|PHPParser/', $class)) {
			return;
		}

		if ($this->classFilter && !$this->classFilter->__invoke($class)) {
			// user's filter wants us to omit this class
			return;
		}

		if (!$this->originalIncludePath || !$this->classyIncludePath) {
			$this->setupIncludePath();
		}

		if (!$this->cache) {
			$this->setupCache();
		}

		// use the original include path so that we don't inadvertantly find classy versions of the file
		ini_set('include_path', $this->originalIncludePath);

		$file = $this->classLocator->__invoke($class);

		// revert back to the classy include path so that any code doing require/include will get our proxies
		ini_set('include_path', $this->classyIncludePath);

		if (!$file) {
			// If we can't find the file, let the autoload continue through the stack.  Some people even have unit tests that check for a specific exception
			// from their autoloaders.
			return false;
		}

		$sourceFile = new \Classy\SourceFile($file, $this->cacheDir);
		$proxy = new \Classy\Parser($sourceFile, $this->cache);

		try {
			$proxy->parse();
		} catch(\Exception $e) {
			throw new \Exception("Unable to generate proxy for $class", null, $e);
		}
	}

	/**
	 * Registers the interceptor onto the spl autoload stack
	 * 
	 * @access public
	 * @return void
	 */
	public function init() {
		spl_autoload_register(array($this, 'autoload'), true, true);
	}

	/**
	 * Sets the given closure to be used to filter out classes from being intercepted.
	 * 
	 * @param \Closure $filter - function that returns true for classes that should be intercepted
	 * @access public
	 * @return void
	 */
	public function setClassFilter(\Closure $filter) {
		$this->classFilter = $filter;
	}

	/**
	 * Helper function to create the cache
	 * 
	 * @access private
	 * @return void
	 */
	private function setupCache() {
		$this->cache = new \Classy\Cache($this->cacheDir);
		$this->cache->import();
		$this->cache->enablePersistance();
	}

	/**
	 * Prepends classy locations to the include_path so our versions of classes take preference for direct includes.  Note, 
	 * this function does not actually call ini_set() but rather stores the new include_path to an instance member for use later.
	 * 
	 * @access private
	 * @return void
	 */
	private function setupIncludePath() {
		// prepend the cache dir to the include path so that any direct requires will hit our proxies instead of the real files
		$this->originalIncludePath = ini_get('include_path');
		$originalIncludePathParts = explode(':', $this->originalIncludePath);
		$classyIncludePath = array();
		foreach ($originalIncludePathParts as $path) {
			if ($path == '.') {
				$path = "";
			}

			$classyIncludePath[] = $this->cacheDir . "{$path}";
		}

		$classyIncludePath = array_merge($classyIncludePath, $originalIncludePathParts);
		$this->classyIncludePath = implode(':', $classyIncludePath);
	}
}
