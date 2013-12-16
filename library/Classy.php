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

/**
 * Classy 
 *
 * This class provides the external interface into classy.  It keeps track of registered proxies and classy configuration.
 * Technically speaking, most of the state data here ($proxies, $classFilter, etc) should really live in some well-defined manager class(es).
 * We use simple static vars/arrays instead solely for performance reasons.  Remember, *every* method call of *every* class will be calling hasProxy/hasStaticProxy so
 * avoiding extra method calls trumps proper OO encapsulation.
 *
 */
class Classy {
	const DEFAULT_CACHE_DIR = '/tmp/classy';

	private static $cacheDir;
	private static $classFilter;
	private static $classLocator;
	private static $interceptor;
	private static $statics = array();
	private static $proxies = array();

	/**
	 * This is for removing any registered proxies or static proxies.  Generally, unit tests will call this during teardown(). 
	 * 
	 * @static
	 * @access public
	 * @return void
	 */
	public static function clear() {
		foreach (self::$proxies as $class => $object) {
			$class::$_classy_hasProxy = false;
		}

		foreach (self::$statics as $class => $object) {
			$class::$_classy_hasStaticProxy = false;
		}

		self::$proxies = array();
		self::$statics = array();
	}

	/**
	 * Returns true if a proxy has been registerd for the given $class 
	 * 
	 * @param string $class - class in question
	 * @static
	 * @access public
	 * @return boolean 
	 */
	public static function hasProxy($class) {
		return isset(self::$proxies[$class]);
	}

	/**
	 * Returns true if a static proxy has been registerd for the given $class 
	 * 
	 * @param string $class - class in question
	 * @static
	 * @access public
	 * @return boolean 
	 */
	public static function hasStaticProxy($class) {
		return isset(self::$statics[$class]);
	}

	/**
	 * Initializes internal Classy state.  This function should be called after all configuration has been done but before loading any other classes.
	 * 
	 * @static
	 * @access public
	 * @return void
	 */
	public static function init() {
		if (!self::$cacheDir) {
			self::$cacheDir = self::DEFAULT_CACHE_DIR;
		}
		
		if (!self::$classLocator) {
			self::$classLocator = self::getDefaultClassLocator();
		}

		if (!is_dir(self::$cacheDir)) {
			throw new Exception("Cache directory does not exist: " . self::$cacheDir);
		}

		if (!is_writable(self::$cacheDir)) {
			throw new Exception("Cache directory is not writable: " . self::$cacheDir);
		}

		$cache = new \Classy\Cache(self::$cacheDir);
		$cache->import();
		$cache->enablePersistance();

		$parserFactory = new \Classy\ParserFactory($cache);
		$sourceFileFactory = new \Classy\SourceFileFactory(self::$cacheDir);
		$iniHelper = new \Classy\IniHelper;
		self::$interceptor = new \Classy\Interceptor(self::$classLocator, $parserFactory, $sourceFileFactory, $iniHelper);

		if (self::$classFilter) {
			self::$interceptor->setClassFilter(self::$classFilter);
		}

		self::$interceptor->init();
	}

	/**
	 * Runs $method($args) on the registered proxy if available.
	 * 
	 * @param string $class - name of class being proxied
	 * @param string $method - method to proxy
	 * @param array $args - array of arguments passed to the original function
	 * @static
	 * @access public
	 * @return mixed - forwards the return of the proxy call
	 */
	public static function proxy($class, $method, array $args) {
		$proxy = self::$proxies[$class];
		if (!$proxy) {
			return;
		}

		return call_user_func_array(array($proxy, $method), $args);
	}

	/**
	 * Sets an instance member on the registered proxy if available.  Note, the property must be public
	 * on the proxy instance.
	 * 
	 * @param string $class - name of class being proxied
	 * @param string $name - property to proxy
	 * @param mixed $value - value of the property
	 * @static
	 * @access public
	 * @return void
	 */
	public static function proxyProperty($class, $name, $value) {
		$proxy = self::$proxies[$class];
		if (!$proxy) {
			return;
		}
	
		// we set this state variable so we can avoid reproxying the set.  For example, the $proxy will likely be an instance
		// of some mock framework (eg Mockery) which Classy is *also* overriding so we could get in an infinite loop.
		$proxy->_classy_inProxyProperty = true;
		$proxy->{$name} = $value;
		$proxy->_classy_inProxyProperty = false;
	}

	/**
	 * Runs $method($args) on the registered static proxy if available.
	 * 
	 * @param string $class - name of class being proxied
	 * @param string $method - method to proxy
	 * @param array $args - array of arguments passed to the original function
	 * @static
	 * @access public
	 * @return mixed - forwards the return of the proxy call
	 */
	public static function proxyStatic($class, $method, array $args) {
		$proxy = self::$statics[$class];
		if (!$proxy) {
			return;
		}

		return call_user_func_array(array($proxy, $method), $args);
	}

	/**
	 * Sets the object to use when proxy() is called for an *instance* method on the given class.  Generally a unit test will use this to set a mock object. 
	 * 
	 * @param string $class - name of class to proxy
	 * @param object $proxy - instance to use as proxy
	 * @static
	 * @access public
	 * @return object - the proxy object passed in (for chaining)
	 */
	public static function registerProxy($class, $proxy) {
		$class::$_classy_hasProxy = true;
		self::$proxies[$class] = $proxy;
		return $proxy;
	}

	/**
	 * Sets the object to use when proxyStatic() is called for a *static* method on the given class.  Generally a unit test will use this to set a mock object. 
	 * 
	 * @param string $class - name of class to proxy
	 * @param object $proxy - instance to use as proxy
	 * @static
	 * @access public
	 * @return object - the proxy object passed in (for chaining)
	 */
	public static function registerStaticProxy($class, $proxy) {
		$class::$_classy_hasStaticProxy = true;
		self::$statics[$class] = $proxy;
		return $proxy;
	}

	/**
	 * Sets the directory to use for all caching and classifized files
	 * 
	 * @param string $dir - directory to use
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setCacheDir($dir) {
		self::$cacheDir = $dir;
	}

	/**
	 * Sets the closure to use for controlling which classes will be classifized
	 * 
	 * @param \Closure $filter - should return true if class should be classifized
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setClassFilter(\Closure $filter) {
		self::$classFilter = $filter;
	}

	/**
	 * Sets the closure to use for mapping class name to source file location
	 * 
	 * @param \Closure $locator - should return path to source file
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setClassLocator(\Closure $locator) {
		self::$classLocator = $locator;
	}

	/**
	 * Returns a PSR-0 compatible class locator.  This is the default implementation.
	 * 
	 * @static
	 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
	 * @access private
	 * @return \Closure
	 */
	private static function getDefaultClassLocator() {
		return function($class) {
			$class = ltrim($class, '\\');
			return strtr($class, array('_' => DIRECTORY_SEPARATOR, '\\' => DIRECTORY_SEPARATOR));
		};
	}
}
