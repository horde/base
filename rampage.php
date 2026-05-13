<?php

if (is_dir(dirname(__FILE__, 3) . '/vendor')) {
    require_once dirname(__FILE__, 3) . '/vendor/autoload.php';
} elseif (is_dir(dirname(__FILE__, 4) . '/vendor')) {
    require_once dirname(__FILE__, 4) . '/vendor/autoload.php';
}

if (file_exists(__DIR__ . '/config/horde.local.php')) {
    require_once __DIR__ . '/config/horde.local.php';
}

Horde\Core\RampageBootstrap::run();
