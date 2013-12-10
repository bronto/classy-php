<?php
namespace Classy\Test;

class ParserTest extends Base {
	public static $forTestParseUnaliasesNamespaces = 'blargl';

	protected function setUp() {
		parent::setUp();

		$this->contentHandler = \Mockery::on(function($contents) {
			//remove the <?php
			$contents = str_replace("<?php", "", $contents);
			eval($contents);
			return true;
		});

		$this->sourceFile = \Mockery::mock('\Classy\SourceFile')
					->shouldIgnoreMissing();
		$this->sourceFile->shouldReceive('writeProxy')
					->with($this->contentHandler)->byDefault();

		$this->cache = \Mockery::mock('\Classy\Cache')
					->shouldIgnoreMissing();
		
	}

	private function runParse($class, $code, \Closure $passthruExpectations=null, \Closure $proxyExpectations=null) {
		$file = "$class.php";

		$this->sourceFile->shouldReceive('getContents')
					->once()
					->andReturn($code);

		$parser = new \Classy\Parser($this->sourceFile, $this->cache);
		$parser->parse();

		$this->assertTrue(class_exists($class, false), "class $class not found");

		$object = new $class;
		$this->assertInstanceOf($class, $object);

		if ($passthruExpectations) {
			$passthruExpectations($object);
		}

		$mock = \Mockery::mock($class);
		\Classy::registerProxy($class, $mock);
		$object = new $class;

		if ($proxyExpectations) {
			$proxyExpectations($object, $mock);
		}
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseEmptyClass() {
		$that = $this;
		$class = 'A' . __LINE__;
		$code = "<?php
			class $class {}
		";
		$passthruExpectations = function($object) use ($that){
			$object->foo = true;
			$that->assertTrue($object->foo);
		};
	
		$this->runParse($class, $code, $passthruExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseConstructor() {
		$that = $this;
		$class = 'A' . __LINE__;
		$code = "<?php
			class $class {
				public function __construct() {
					\$this->foo = true;
				}
			}
		";
		$proxyExpectations = function($object) use ($that){
			// constructors are always overridden
			$that->assertNull($object->foo);
		};
	
		$this->runParse($class, $code, null, $proxyExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParsePublicMethod() {
		$that = $this;
		$class = 'A' . __LINE__;
		$code = "<?php
			class $class {
				public function foo() {
					return true;
				}
			}
		";
		$passthruExpectations = function($object) use ($that){
			$that->assertTrue($object->foo());
		};
		$proxyExpectations = function($object, $mock) use ($that){
			$mock->shouldReceive('foo')->once()->andReturn('foo');
			$that->assertEquals('foo', $object->foo());
		};

		$this->runParse($class, $code, $passthruExpectations, $proxyExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParsePrivateMethod() {
		$that = $this;
		$class = 'A' . __LINE__;
		$code = "<?php
			class $class {
				private function foo() {
					return true;
				}
			}
		";
		$passthruExpectations = function($object) use ($that){
			$method = new \ReflectionMethod($object, 'foo');
			$method->setAccessible(true);
			$that->assertTrue($method->invoke($object));
		};

		$this->runParse($class, $code, $passthruExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseInheritedMethod() {
		$that = $this;
		$parent = 'A' . __LINE__;
		$child = 'B' . __LINE__;
		$code = "<?php
			class $parent {
				public function foo() {
					return true;
				}
			}
			class $child extends $parent {}
		";
		$passthruExpectations = function($object) use ($that){
			$that->assertTrue($object->foo());
		};
		$proxyExpectations = function($object, $mock) use ($that){
			$mock->shouldReceive('foo')->once()->andReturn('foo');
			$that->assertEquals('foo', $object->foo());
		};

		$this->runParse($child, $code, $passthruExpectations, $proxyExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseSetter() {
		$that = $this;
		$class = 'A' . __LINE__;
		$code = "<?php
			class $class {
				public function __set(\$name, \$value) {
					\$this->bar = true;
				}
			}
		";
		$passthruExpectations = function($object) use ($that){
			$object->foo = 5;
			$that->assertTrue($object->bar);
		};
		$proxyExpectations = function($object, $mock) use ($that){
			// proxying a property is in addition to the normal setter behavior
			$object->foo = 'foo';
			$that->assertEquals('foo', $mock->foo);
			$that->assertNull($object->foo);
			$that->assertTrue($object->bar);
		};

		$this->runParse($class, $code, $passthruExpectations, $proxyExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseSetterCalledMultipleTimes() {
		$that = $this;
		$class = 'A' . __LINE__;
		$code = "<?php
			class $class {
				public function __set(\$name, \$value) {
					\$this->bar = true;
				}
			}
		";
		$proxyExpectations = function($object, $mock) use ($that){
			$object->foo = 'foo';
			$that->assertEquals('foo', $mock->foo);
			$object->foo = 'baz';
			$that->assertEquals('baz', $mock->foo);
		};

		$this->runParse($class, $code, null, $proxyExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseInheritedSetter() {
		$that = $this;
		$parent = 'A' . __LINE__;
		$child = 'B' . __LINE__;
		$code = "<?php
			class $parent {
				public function __set(\$name, \$value) {
					\$this->bar = true;
				}
			}
			class $child extends $parent {}
		";
		$passthruExpectations = function($object) use ($that){
			$object->foo = 5;
			$that->assertTrue($object->bar);
		};
		$proxyExpectations = function($object, $mock) use ($that){
			$object->foo = 'foo';
			$that->assertEquals('foo', $mock->foo);
			$that->assertNull($object->foo);
			$that->assertTrue($object->bar);
		};

		$this->runParse($child, $code, $passthruExpectations, $proxyExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseInheritedConstructor() {
		$that = $this;
		$parent = 'A' . __LINE__;
		$child = 'B' . __LINE__;
		$code = "<?php
			class $parent {
				public function __construct() {
					\$this->foo = true;
				}
			}
			class $child extends $parent {}
		";
		$passthruExpectations = function($object) use ($that){
			$that->assertTrue($object->foo);
		};
		$proxyExpectations = function($object, $mockChild) use ($that, $parent){
			$that->assertNull($object->foo);
		};

		$this->runParse($child, $code, $passthruExpectations, $proxyExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseSetterInChildAndParent() {
		$that = $this;
		$parent = 'A' . __LINE__;
		$child = 'B' . __LINE__;
		$code = "<?php
			class $parent {
				public function __set(\$name, \$value) {
					\$this->meh = true;
				}
			}
			class $child extends $parent {
				public function __set(\$name, \$value) {
					\$this->bar = true;
					parent::__set(\$name, \$value);
				}
			}
		";
		$passthruExpectations = function($object) use ($that){
			$object->foo = 5;
			$that->assertTrue($object->bar);
		};
		$proxyExpectations = function($object, $mockChild) use ($that, $parent){
			$mockParent = \Mockery::mock($parent);
			\Classy::registerProxy($parent, $mockParent);

			$object->foo = 'foo';
			$that->assertEquals('foo', $mockChild->foo);
			$that->assertNull($object->foo);
			$that->assertTrue($object->bar);
			$that->assertTrue($object->meh);

			// ensure the proxy doesn't occur on the parent as well
			$that->assertNull($mockParent->foo);
		};

		$this->runParse($child, $code, $passthruExpectations, $proxyExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseNonClassStatements() {
		$that = $this;
		$class = 'A' . __LINE__;
		$code = "<?php
			
			\$foo = 1;

			class $class {
				public static \$bar = 0;
			}

			$class::\$bar = \$foo;
		";
		$passthruExpectations = function($object) use ($that, $class){
			$that->assertEquals(1, $class::$bar);
		};

		$this->runParse($class, $code, $passthruExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseNamespace() {
		$class = 'A' . __LINE__;
		$code = "<?php
			namespace Classy\\Test;

			class $class {}
		";

		$this->runParse("\\Classy\\Test\\$class", $code);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseAlias() {
		$that = $this;
		$class = 'A' . __LINE__;
		$code = "<?php
			use \\Classy\\Test\\ParserTest as Bar;

			class $class {
				public function meh(){
					return Bar::\$forTestParseUnaliasesNamespaces;
				}
			}
		";
		$passthruExpectations = function($object) use ($that){
			$that->assertEquals(\Classy\Test\ParserTest::$forTestParseUnaliasesNamespaces, $object->meh());
		};

		$this->runParse($class, $code, $passthruExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseGetter() {
		$that = $this;
		$class = 'A' . __LINE__;
		$code = "<?php
			class $class {
				public function __get(\$name){
					return true;
				}
			}
		";
		$passthruExpectations = function($object) use ($that){
			$that->assertTrue($object->foo);
		};
		$proxyExpectations = function($object, $mock) use ($that){
			$object->foo = 42;
			$that->assertTrue($object->bar);
			$that->assertEquals(42, $mock->foo);
			$that->assertEquals(42, $object->foo);
		};

		$this->runParse($class, $code, $passthruExpectations, $proxyExpectations);
	}
		
	/**
	 * This particular test essentially checks that all classy internal variables are properly defined.  If one is not defined, then the exception
	 * will be thrown. This actually happened with a real class that had an interesting __get.
	 * @covers Classy\Parser::parse
	 */
	public function testParseGetterThrowsExceptionForUndefinedProperties() {
		$that = $this;
		$class = 'A' . __LINE__;
		$code = "<?php
			class $class {
				public function __get(\$name){
					if (!isset(\$this->\$name)) {
						throw new \Exception(\"Undeclared instance property was accessed: \$name\");
					}
				}
			}
		";
		$proxyExpectations = function($object, $mock) use ($that){
			$object->foo = 42;
			$that->assertEquals(42, $mock->foo);
			$that->assertEquals(42, $object->foo);
		};

		$this->runParse($class, $code, null, $proxyExpectations);
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParsePrivateConstructorPlusNoInheritedConstructor() {
		$that = $this;
		$parent = 'A' . __LINE__;
		$child = 'B' . __LINE__;
		$code = "<?php
			class $parent {}

			class $child extends $parent {
				private function __construct(){}
			}
		";

		$file = "$class.php";
		$this->sourceFile->shouldReceive('getContents')
					->once()
					->andReturn($code);

		$parser = new \Classy\Parser($this->sourceFile, $this->cache);
		$parser->parse();

		$this->assertTrue(class_exists($child, false), "class not found");
	}

	/**
	 * @covers Classy\Parser::parse
	 */
	public function testParseInterface() {
		$class = "A" . __LINE__;
		$code = "<?php
			interface $class {
				public function foo();
			}
		";

		$file = "$class.php";
		$this->sourceFile->shouldReceive('getContents')
					->once()
					->andReturn($code);

		$parser = new \Classy\Parser($this->sourceFile, $this->cache);
		$parser->parse();

		$this->assertTrue(interface_exists($class, false), "interface not found");
	}
}
