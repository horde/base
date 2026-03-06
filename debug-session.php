<?php
/**
 * Debug endpoint to inspect session credentials
 * DELETE THIS FILE after testing!
 */

require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('horde', ['authentication' => 'throw']);

$registry = $GLOBALS['injector']->getInstance('Horde_Registry');

header('Content-Type: application/json');

echo json_encode([
    'authenticated' => $registry->isAuthenticated(),
    'username' => $registry->getAuth(),
    'session_id' => session_id(),
    'session_data' => $_SESSION['__horde']['auth'] ?? null,
], JSON_PRETTY_PRINT);
