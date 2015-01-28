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
 * Interceptor
 *
 * This class attempts to intercept all client classes during autoload.  It accomplishes this by 
 * prepending its own autoloader to the spl stack as well as overriding the include_path in favor of classifized
 * versions of the client class.  The intercepting autoloader runs the client class through our own classifizer
 * and then performs a require_once on the resulting class file.
 */
class Interceptor {
	private $classFilter;
	private $classLocator;
	private $classyIncludePath;
	private $iniHelper;
	private $originalIncludePath;
	private $parserFactory;
	private $sourceFileFactory;

	/**
	 * Constructor
	 * 
	 * @param \Closure $classLocator - function to use to map class name to file location
	 * @param \Classy\ParserFactory $parserFactory - factory object for creating parsers
	 * @param \Classy\SourceFileFactory $sourceFileFactory - factory object for creating sourceFiles
	 * @param \Classy\IniHelper $iniHelper - helper object for interacting with the ini settings
	 * @access public
	 * @return void
	 */
	public function __construct(\Closure $classLocator, \Classy\ParserFactory $parserFactory, \Classy\SourceFileFactory $sourceFileFactory, \Classy\IniHelper $iniHelper) {
		$this->classLocator = $classLocator;
		$this->iniHelper = $iniHelper;
		$this->parserFactory = $parserFactory;
		$this->sourceFileFactory = $sourceFileFactory;
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
	 * @param string $class - name of class to load
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

		$currentIncludePath = $this->iniHelper->get('include_path');

		// we need to reset the classyIncludePath if it:
		// 1.  has not been set yet
		// 2.  the current include path is different from the one we parsed earlier.  However, autoload() will be called recursively for parent classes
		//     so we need to make sure the "change" is not just us having used the classyIncludePath.  Outside of inheritance recursion, the current
		//     include_path should not be classfized at this point. 
		if (!$this->originalIncludePath || !$this->classyIncludePath || 
			($currentIncludePath != $this->classyIncludePath && $currentIncludePath != $this->originalIncludePath)) {
			$this->setupIncludePath($currentIncludePath);
		}

		// ensure we use the original include path so that we don't inadvertantly find classy versions of the file.  The include_path will be
		// classifized right now only if we have been called via inheritance recursion.
		$this->iniHelper->set('include_path', $this->originalIncludePath);

		$file = $this->classLocator->__invoke($class);

		if (!$file) {
			// If we can't find the file, let the autoload continue through the stack.  Some people even have unit tests that check for a specific exception
			// from their autoloaders.
			return false;
		}

		$sourceFile = $this->sourceFileFactory->create($file);
		$parser = $this->parserFactory->create($sourceFile);

		try {
			// revert back to the classy include path so that any code doing require/include will get our proxies
			$this->iniHelper->set('include_path', $this->classyIncludePath);

			$parser->parse();

			// return to the original include path in case the caller modifies it
			$this->iniHelper->set('include_path', $this->originalIncludePath);
		} catch(\Exception $e) {
			$this->iniHelper->set('include_path', $this->originalIncludePath);
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
	 * Prepends classy locations to the include_path so our versions of classes take preference for direct includes.  Note, 
	 * this function does not actually call ini_set() but rather stores the new include_path to an instance member for use later.
	 * 
	 * @access private
	 * @param string $includePath - current include_path
	 * @return void
	 */
	private function setupIncludePath($includePath) {
		// prepend the cache dir to the include path so that any direct requires will hit our proxies instead of the real files
		$this->originalIncludePath = $includePath;
		$originalIncludePathParts = explode(':', $this->originalIncludePath);
		$classyIncludePath = array();
		foreach ($originalIncludePathParts as $path) {
			if ($path == '.') {
				$path = "";
			}

			$classyIncludePath[] = $this->sourceFileFactory->proxyDir . "{$path}";
		}

		$classyIncludePath = array_merge($classyIncludePath, $originalIncludePathParts);
		$this->classyIncludePath = implode(':', $classyIncludePath);
	}
}
