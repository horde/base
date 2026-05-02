<?php

/**
 * Admin Dashboard helper entry point.
 *
 * For environments without URL rewriting, this script redirects to the
 * router-handled /admin/ route.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration'],
]);
require_once dirname(__DIR__) . '/rampage.php';

