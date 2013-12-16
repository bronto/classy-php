# Classy #

Classy is a library that allows unit tests to override behavior in untestable class methods such as those that make static method invocations or those that perform direct class instantiations (ie new Foo).  It is not intended to be a substitute for Dependency Injection and/or the Service Locator pattern but rather an option for times when those patterns are simply unfeasible, be it due to time constraints, risk aversion, performance considerations, or even third party APIs.

Classy works by intercepting the autoloading process and adding its own code to the original class.  This allows Classy to dynamically forward method calls and property sets on your classes to whatever object you have indicated.  Proxy behavior can be reset between unit tests even though the class definition will only be loaded once per process.  

Remember, Classy is *NOT* a mock framework or unit testing framework.  It is intended to work in coordination with both.

## Installation ##

Classy can be installed via the following methods:

### PEAR ###
Classy is hosted on the [bronto](http://bronto.github.io/pear/) PEAR channel.  You can install it by running:

	sudo pear channel-discover bronto.github.io/pear
	sudo pear install bronto.github.io/pear/Classy-beta

## Note on Perfomance ##
Classy attempts to override every method of every non-PHP internal class, including 3rd party libraries.  Parsing all this source code and adding in substitutions is very costly.  As such, Classy writes the Classifized versions to disk and keeps track of when that version was created.  On subsequent unit test runs, Classy will only reparse a class if the source file has been modified since the last Classifized version was made.  This means that initial unit test runs may be very slow (our large code base takes ~30s) but subsequent runs more closely match native behavior.  There is still some overhead with having to check for proxies on every method call/property set, but this tends to be much more acceptable.

## Simple Example ##

Original Class
```PHP
class Secretary {
	public static function callTom(){
		$tom = \Person::getByName('Tom');
		$phone = new \Phone;
		$phone->line = \Phone::LINE_1;
		$phone->dial($tom);
	}
}
```

Test Class
```PHP
// this could go in your Bootstrap.php
require_once('Classy/bootstrap.php');
\Classy::init();

class SecretaryTest extends PHPUnit_Framework_TestCase {
	protected function tearDown() {
		\Classy::clear();
	}

	public function testCallTomDialsTheRightPersonOnTheRightLine() {
		$tom = \Mockery::mock('Person');

		$person = \Classy::registerStaticProxy('Person', \Mockery::mock('Person'));
		$person->shouldReceive('getByName')
				->with('Tom')
				->once()
				->andReturn($tom);

		$phone = \Classy::registerProxy('Phone', \Mockery::mock('Phone'));
		$phone->shouldReceive('dial')
				->with($tom)
				->once();

		\Secretary::callTom();

		$this->assertEquals(\Phone::LINE_1, $phone->line);
	}
}
```

## API ##
### Registers ###
```PHP 
registerProxy($class, $proxy) 
```
Registers an object to forward all instance method invocations and instance property sets for a class.  Class instantiations will no longer call the original constructor nor the original methods.  The only exception is custom __set(); the original __set will be called in addition to proxying the property set.  This is because calling code may rely on whatever the original __set was supposed to do.

Note that multiple instantiations of the class will still result in multiple instances being created although all calls/properties will be forwarded to only 1 proxy.

```PHP 
registerStaticProxy($class, $proxy) 
```
Registers an object to forward all static method calls for a class.

### Configuration ###
```PHP 
setCacheDir($dir) 
````
Sets the path to use for all caching.  For performance reasons, Classy has to cache it's custom versions of your real classes.  Unless overridden with this function, Classy will use the default: /tmp/classy

```PHP 
setClassFilter(function($class){
	// return true if classy should still override this class
}) 
```
Since classes can only be loaded once in PHP, Classy tries to override every class possible.  That way any subsequent unit test will have the ability to register proxies for that class.  Use setClassFilter() to tell classy *not* to override particular classes.  Generally, this should not be needed although there are edge cases in which Classy will not be able to run the original implementation correctly (see Known Issues below).  If set, Classy will only load a class if the function returns true for it.

```PHP 
setClassLocator(function($class) {
	// return file location of class
})
```
Classy needs to be able to get the source code for a class before it is loaded.  Many projects use custom autoloaders that may very wildly in how a class name is mapped to a file location.  This function allows you to provide any custom logic classy needs to find your files.  In general, this will be almost identical to your custom autloader except that this function should *not* actually require/include the file. 

By default, Classy will use a PSR-0 compliant locator.

### Misc ###
```PHP
init()
```
Call this to signal configuration is complete and that Classy should initiate itself.  This function must be called before any proxy registration occurs.

## Known issues ##
* Direct file includes/requires
 * Many older projects directly include source files.  Classy is only able to intercept classes loaded via the autoloader.  Ideally, you should never perform direct includes but rather rely on an autoloader.
 * Classy *does* add a blank version of the original source file to the include path so any subsequent direct includes/requires will not result in duplicate class exceptions.  This feature will only work with relative file paths though.
* include_path
 * Classy adds its own cache directories to the include_path at autoload time.  This is to support the above mentioned include/require feature.  The current implementation will support callers modifying the include_path anywhere EXCEPT: within the classLocator, within an autoloader, or non-class statements within an autoloaded file.  For example, the following will break:
```PHP
ini_set('include_path', '/foo/bar');
class Baz {
}
```
 while the following will work:
```PHP
class Baz {
	public function setIncludePath() {
		ini_set('include_path', '/foo/bar');
	}
}
```
* __autoload
 * Classy relies on the spl_autoload_register stack for interception.  Since direct __autoload() invocations always call your original implementation, Classy will not be able to intercept these classes.  Please always use spl_autoload() or rely on automatic loading.
* Target Classes
 * PHP allows instance methods to be called statically as long as the method does not reference $this.  However, doing so may still result in an instance call (ie $this exists) even though the code explicitly called it statically (ie with ::).  This can lead to confusing proxy behavior since Classy may incorrectly route your call to an instance proxy when you thought it would be a static proxy.  The solution is to follow best practices and formally declare statically called methods as static.
* PHPUnit
 * By default PHPUnit mock objects stub ALL methods of the real class. Since Classy adds its own __set() for proxying public properties, this means that PHPUnit overrides Classy's __set(), causing public properties not to work.  Currently there are the following workarounds:
	1.  Use a different mock framework, such as Mockery, that ignores magic methods
	2.  Tell PHPUnit not to stub __set()
		* `$mock = $this->getMock('Foo', null);`
		* `$mock = $this->getMock('Foo', array('methodThatIsNot__set'))`
