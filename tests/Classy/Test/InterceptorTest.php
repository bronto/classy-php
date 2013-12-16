<?php
namespace Classy\Test;

class InterceptorTest extends Base {
	private $autoload;
	private $classLocator;
	private $iniHelper;
	private $parser;
	private $parserFactory;
	private $sourceFile;
	private $sourceFileFactory;
	private $originalIncludePath;

	protected function setUp() {
		parent::setUp();

		$this->classLocator = function(){
			return 'foo';
		};

		$this->sourceFile = \Mockery::mock('\Classy\SourceFile');
		$this->parser = \Mockery::mock('\Classy\Parser')
							->shouldIgnoreMissing();

		$this->iniHelper = \Mockery::mock('\Classy\IniHelper')
							->shouldIgnoreMissing();
		$this->parserFactory = \Mockery::mock('\Classy\ParserFactory')
							->shouldIgnoreMissing();
		$this->parserFactory->shouldReceive('create')->andReturn($this->parser);

		$this->sourceFileFactory = \Mockery::mock('\Classy\SourceFileFactory')
							->shouldIgnoreMissing();
		$this->sourceFileFactory->proxyDir = '/classy';
		$this->sourceFileFactory->shouldReceive('create')->andReturn($this->sourceFile);

		$method = new \ReflectionMethod('\Classy\Interceptor', 'autoload');
		$method->setAccessible(true);
		$this->autoload = $method;
	}

	public function testAutoloadIncludePath() {
		$this->iniHelper->shouldReceive('get')->with('include_path')->once()->andReturn('/bar');

		$this->iniHelper->shouldReceive('set')->with('include_path', '/bar')->once()->ordered();
		$this->iniHelper->shouldReceive('set')->with('include_path', '/classy/bar:/bar')->once()->ordered();
		$this->parser->shouldReceive('parse')->once()->ordered();
		$this->iniHelper->shouldReceive('set')->with('include_path', '/bar')->once()->ordered();

		$interceptor = new \Classy\Interceptor($this->classLocator, $this->parserFactory, $this->sourceFileFactory, $this->iniHelper);
		$this->autoload->invoke($interceptor, 'foo');
	}

	public function testAutoloadIncludePathUsesOriginalIfClassifized() {
		$interceptor = new \Classy\Interceptor($this->classLocator, $this->parserFactory, $this->sourceFileFactory, $this->iniHelper);

		$this->iniHelper->shouldReceive('get')->with('include_path')->once()->andReturn('/bar');
		$this->autoload->invoke($interceptor, 'foo');

		$this->iniHelper->shouldReceive('get')->with('include_path')->once()->andReturn('/classy/bar:/bar');

		$this->iniHelper->shouldReceive('set')->with('include_path', '/bar')->once()->ordered();
		$this->iniHelper->shouldReceive('set')->with('include_path', '/classy/bar:/bar')->once()->ordered();
		$this->parser->shouldReceive('parse')->once()->ordered();
		$this->iniHelper->shouldReceive('set')->with('include_path', '/bar')->once()->ordered();

		$this->autoload->invoke($interceptor, 'foo');
	}

	public function testAutoloadIncludePathSupportsChanges() {
		$interceptor = new \Classy\Interceptor($this->classLocator, $this->parserFactory, $this->sourceFileFactory, $this->iniHelper);

		$this->iniHelper->shouldReceive('get')->with('include_path')->once()->andReturn('/bar');
		$this->autoload->invoke($interceptor, 'foo');

		$this->iniHelper->shouldReceive('get')->with('include_path')->once()->andReturn('/meh');

		$this->iniHelper->shouldReceive('set')->with('include_path', '/meh')->once()->ordered();
		$this->iniHelper->shouldReceive('set')->with('include_path', '/classy/meh:/meh')->once()->ordered();
		$this->parser->shouldReceive('parse')->once()->ordered();
		$this->iniHelper->shouldReceive('set')->with('include_path', '/meh')->once()->ordered();

		$this->autoload->invoke($interceptor, 'foo');
	}
}
