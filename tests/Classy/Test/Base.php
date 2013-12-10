<?php
namespace Classy\Test;

class Base extends \PHPUnit_Framework_TestCase {
	protected function tearDown() {
		\Mockery::close();
		\Classy::clear();
	}
}
