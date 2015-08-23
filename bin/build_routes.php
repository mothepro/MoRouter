<?php
require 'vendor/autoload.php';

/**
 * Saves router in file
 * 
 * build_routes.php input output
 * 
 * In application:
 * $slim->router = unserialize(output);
 */
$slim = new Slim\Slim;
Mo\Router\Router::generate($slim->router, $argv[1]);
file_put_contents($argv[2], serialize($slim->router));