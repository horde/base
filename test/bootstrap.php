<?php

declare(strict_types=1);

/**
 * Bootstrap file for PHPUnit tests
 */

// Find and load Composer autoloader
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../../../../running/horde/vendor/autoload.php',
    __DIR__ . '/../../bundle/vendor/autoload.php',
];

$autoloaderFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    fwrite(STDERR, "Could not find Composer autoloader. Run 'composer install' first.\n");
    fwrite(STDERR, "Tried paths:\n");
    foreach ($autoloadPaths as $path) {
        fwrite(STDERR, "  - $path\n");
    }
    exit(1);
}

// Set timezone for tests
date_default_timezone_set('UTC');
