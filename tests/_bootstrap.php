<?php

use Pimcore\Tests\Util\Autoloader;

include __DIR__ . '/../../../../vendor/autoload.php';

\Pimcore\Bootstrap::setProjectRoot();
\Pimcore\Bootstrap::boostrap();

Autoloader::addNamespace('Pimcore\Model\DataObject', __DIR__ . '/_output/var/classes/DataObject');
Autoloader::addNamespace('Pimcore\Tests\Cache', __DIR__ . '/cache');
Autoloader::addNamespace('Pimcore\Tests\Ecommerce', __DIR__ . '/ecommerce');
Autoloader::addNamespace('Pimcore\Tests\Model', __DIR__ . '/model');
Autoloader::addNamespace('Pimcore\Tests\Unit', __DIR__ . '/unit');
Autoloader::addNamespace('Pimcore\Tests\Rest', __DIR__ . '/rest');

if (!defined('TESTS_PATH')) {
    define('TESTS_PATH', __DIR__);
}

define('PIMCORE_TEST', true);
