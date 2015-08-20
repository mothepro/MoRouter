<?php
require 'vendor/autoload.php';
		
$slim = new Slim\Slim;
Mo\Router\Router::generate($slim->router, $argv[1]);
print_r($slim->router);