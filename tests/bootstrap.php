<?php

//load testing dependancies
require_once 'Mockery/Loader.php';
require_once 'Hamcrest/Hamcrest.php';
$loader = new \Mockery\Loader;
$loader->register();

// load Classy library
$root    = realpath(dirname(dirname(__FILE__)));
$library = "$root/library";
$tests   = "$root/tests";

$path = array(
    $library,
    $tests,
    get_include_path(),
);
set_include_path(implode(PATH_SEPARATOR, $path));

require_once dirname(__DIR__) . '/library/bootstrap.php';

