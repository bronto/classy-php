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
 * @subpackage  NodeVisitor
 * @author      Ed Dawley <ed@eddawley.com>
 * @copyright   2013 Bronto Software Inc.
 * @license     http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link        https://github.com/bronto/classy-php
 */

namespace Classy\NodeVisitor;

/**
 * Classifizer
 *
 * This is the custom NodeVisitor used to rewrite a class during parsing.  This class will substitute hard-coded values
 * for __FILE__/__DIR__ constants, remove USE statetments, and add in stub statements to methods of the original source.
 */
class Classifizer extends \PHPParser_NodeVisitorAbstract {
	private $classNode;
	private $classHasSetter;
	private $interfaceNode;
	private $sourceFile;

	private static $constructorOverride;
	private static $methodOverride;
	private static $objectPropertyDeclarations;
	private static $parser;
	private static $printer;
	private static $setOverride;
	private static $setOverrideMissing;

	public function enterNode(\PHPParser_Node $node) {
		if ($node instanceof \PHPParser_Node_Stmt_Class) {
			$this->classNode = $node;
			return;
		}

		if ($node instanceof \PHPParser_Node_Stmt_Interface) {
			$this->interfaceNode = $node;
			return;
		}
	}

	/**
	 * Helper function to initialize static members.  Most of these variables are static solely for performance reasons since they
	 * can be reused across all classes being parsed.
	 * 
	 * @static
	 * @access public
	 * @return void
	 */
	public static function initStatic() {
		self::$parser = new \PHPParser_Parser(new \PHPParser_Lexer);
		self::$printer = new \PHPParser_PrettyPrinter_Default();

		// we never want to proxy constructors to Classy.  We just want to use a blank constructor if the class is proxied
		self::$constructorOverride = self::$parser->parse("<?php 
			if (static::\$_classy_hasProxy) {
				return;
			}
		");

		// we use the presence of $this in order to determine a static call.  This is to support instance methods being called statically.
		self::$methodOverride = self::$parser->parse("<?php 
			if (static::\$_classy_hasProxy && is_object(\$this)) {
				return \\Classy::proxy(get_called_class(), __FUNCTION__, func_get_args());
			}

			if (static::\$_classy_hasStaticProxy && !is_object(\$this)) {
				return \\Classy::proxyStatic(get_called_class(), __FUNCTION__, func_get_args());
			}
		");

		// NOTE: it is of utmost importance that *all* of our internal variables are defined here.  If one is ommitted, it's possible that a class's
		// real __get will do something custom (ie wrong) for our variables.
		self::$objectPropertyDeclarations = self::$parser->parse("<?php 
			class Foo {
				public static \$_classy_hasProxy;
				public static \$_classy_hasStaticProxy;
				public \$_classy_inProxyProperty;
			}
		");

		//  our __set override
		self::$setOverride = self::$parser->parse("<?php
			// ensure classy variables are handled irrespective of rest of __set
			if (\$name == '_classy_inProxyProperty') {
				\$this->\$name = \$value;
				return;
			}

			// short-circuit if we're being called externally
			if (\$this->_classy_inProxyProperty) {
				\$this->\$name = \$value;
				return;
			}

			// proxy it.  note that this could be the second, third, etc time this is called if the real __set code 
			// keeps calling parent::__set.  
			// we deem multiple calls ok though since we're just overwriting the same value over and over.  You can't 
			// add expectations concerning call counts to property sets of mock objects.
			if (static::\$_classy_hasProxy) {
				\\Classy::proxyProperty(get_called_class(), \$name, \$value);
			}
		");

		self::$setOverrideMissing = self::$parser->parse("<?php
			class Foo {
				public function __set(\$name, \$value) {
					// ensure classy variables are handled irrespective of rest of __set
					if (\$name == '_classy_inProxyProperty') {
						\$this->\$name = \$value;
						return;
					}

					if (\$this->_classy_inProxyProperty) {
						\$this->\$name = \$value;
						return;
					}

					// see comment in setOverride
					if (static::\$_classy_hasProxy) {
						\\Classy::proxyProperty(get_called_class(), \$name, \$value);
					}

					if (is_callable('parent::__set')) {
						parent::__set(\$name, \$value);
						return;
					}

					\$this->\$name = \$value;
				}
			}
		");

		self::$setOverrideMissing = self::$setOverrideMissing[0]->stmts[0];

		// we just want the statements 
		self::$objectPropertyDeclarations = self::$objectPropertyDeclarations[0]->stmts;
	}

	public function leaveNode(\PHPParser_Node $node) {
		if ($node instanceof \PHPParser_Node_Scalar_FileConst) {
			return new \PHPParser_Node_Scalar_String($this->sourceFile->getFile());
		}

		if ($node instanceof \PHPParser_Node_Scalar_DirConst) {
			return new \PHPParser_Node_Scalar_String($this->sourceFile->getDir());
		}

		if ($node instanceof \PHPParser_Node_Stmt_Interface) {
			unset($this->interfaceNode);
			return;
		}

		if ($node instanceof \PHPParser_Node_Stmt_ClassMethod) {
			return $this->leaveMethod($node);
		}

		if ($node instanceof \PHPParser_Node_Stmt_Class) {
			return $this->leaveClass($node);
		}

		if ($node instanceof \PHPParser_Node_Stmt_Use) {
			// remove any "use X" statements since we have already fully-qualified every namespace reference
			return false;
		}
	}

	/**
	 * Called when leaving a method node (ie when we're currently rewriting a method)
	 * 
	 * @param \PHPParser_Node $method 
	 * @access private
	 * @return mixed - see return of leaveNode()
	 */
	private function leaveMethod(\PHPParser_Node $method) {
		// don't modify abstract methods
		if ($method->isAbstract()) {
			return;
		}

		if ($this->interfaceNode) {
			// we don't change anything about interfaces.  short-circuit
			return;
		}

		if ($method->name == '__construct' || $method->name == $this->classNode->name) {
			$method->stmts = array_merge(self::$constructorOverride, $method->stmts);
		} else if ($method->name == '__set') {
			$method->stmts = array_merge(self::$setOverride, $method->stmts);
			$this->classHasSetter = true;
		} else if ($method->name == '__get') {
			// we don't want to add anything to a custom getter
			return null;
		} else {
			$method->stmts = array_merge(self::$methodOverride, $method->stmts);
		}
	}

	/**
	 * Called when leaving a class node (ie when we're currently rewriting a class)
	 * 
	 * @param \PHPParser_Node $class 
	 * @access private
	 * @return mixed - see return of leaveNode()
	 */
	private function leaveClass(\PHPParser_Node $class) {
		if (!$this->classHasSetter) {
			$class->stmts[] = self::$setOverrideMissing;
		}

		// add in the object property declarations
		$class->stmts = array_merge(self::$objectPropertyDeclarations, $class->stmts);

		unset($this->classNode);
		unset($this->classHasSetter);
	}

	public function setSourceFile(\Classy\SourceFile $sourceFile) {
		$this->sourceFile = $sourceFile;
	}
}

Classifizer::initStatic();
