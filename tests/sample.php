<?php
require 'vendor/autoload.php';
		
$slim = new Slim\Slim;
Mo\Router\Router::generate($slim->router, $argv[1]);
var_dump($slim->router);