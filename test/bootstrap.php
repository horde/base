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
        $classLoader = require $path;
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

// Register symlinked packages not managed by composer lock
if (isset($classLoader) && $classLoader instanceof Composer\Autoload\ClassLoader) {
    $identityPath = dirname(__DIR__) . '/vendor/horde/identity/src/';
    if (is_dir($identityPath)) {
        $classLoader->addPsr4('Horde\\Identity\\', $identityPath);
    }
}

// Set timezone for tests
date_default_timezone_set('UTC');
