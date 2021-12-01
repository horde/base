<?php
$candidates = [
    dirname(__FILE__, 2) . '/vendor/autoload.php',
    dirname(__FILE__, 4) . '/autoload.php',
];
// Cover root case and library case
foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        break;
    }
}
Horde\Test\Bootstrap::bootstrap(__DIR__);
// Fall back to the Horde Autoloader. Horde Applications' lib/ is not a true PSR-0 layout as far as composer is concerned.
Horde\Test\Autoload::addPrefix('Skeleton', dirname(__FILE__, 2) . '/lib');
