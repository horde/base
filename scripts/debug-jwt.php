<?php

/**
 * Debug endpoint to inspect JWT and session
 * DELETE THIS FILE after testing!
 */

require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('horde', ['authentication' => 'none']);

$registry = $GLOBALS['injector']->getInstance('Horde_Registry');

// Check if there's JWT bootstrap in session
$jwtBootstrap = $_SESSION['__horde']['jwt_bootstrap'] ?? null;

// Parse JWT tokens from localStorage if provided
$accessToken = $_GET['access_token'] ?? null;
$refreshToken = $_GET['refresh_token'] ?? null;

$accessTokenData = null;
$refreshTokenData = null;

if ($accessToken) {
    $parts = explode('.', $accessToken);
    if (count($parts) === 3) {
        $accessTokenData = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    }
}

if ($refreshToken) {
    $parts = explode('.', $refreshToken);
    if (count($parts) === 3) {
        $refreshTokenData = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    }
}

header('Content-Type: application/json');

echo json_encode([
    'authenticated' => $registry->isAuthenticated(),
    'username' => $registry->getAuth(),
    'session_id' => session_id(),
    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive',
    'jwt_bootstrap_present' => $jwtBootstrap !== null,
    'jwt_bootstrap' => $jwtBootstrap,
    'access_token_claims' => $accessTokenData,
    'refresh_token_claims' => $refreshTokenData,
    'session_matches_jti' => $refreshTokenData && session_id() === ($refreshTokenData['jti'] ?? null),
    'session_data' => $_SESSION['__horde']['auth'] ?? null,
], JSON_PRETTY_PRINT);
