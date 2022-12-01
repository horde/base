<?php
if (is_dir(dirname(__FILE__, 3) . '/vendor')) {
    require_once dirname(__FILE__, 3) . '/vendor/autoload.php';
} elseif (is_dir(dirname(__FILE__, 4) . '/vendor')) {
    require_once dirname(__FILE__, 4) . '/vendor/autoload.php';
}
require_once dirname(__FILE__) . '/lib/core.php';
Horde\Core\RampageBootstrap::run();
