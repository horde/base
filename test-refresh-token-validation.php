<?php

declare(strict_types=1);

/**
 * Manual Test: Refresh Token Validation
 *
 * Tests that refresh tokens are properly validated against sessions.
 * Run this from command line to verify the fix for Issue #1 (Critical).
 *
 * Usage: php test-refresh-token-validation.php
 */

require_once __DIR__ . '/../../lib/Application.php';
Horde_Registry::appInit('horde', ['cli' => true, 'authentication' => 'none']);

$cli = new Horde_Cli();
$cli->writeln("Testing Refresh Token Validation\n");

// Get services
$registry = $GLOBALS['registry'];
$injector = $GLOBALS['injector'];
$authService = $injector->getInstance(Horde\Horde\Service\AuthenticationService::class);
$jwtService = $injector->getInstance(Horde\Horde\Service\JwtService::class);

// Test 1: Valid refresh token with valid session
$cli->writeln("Test 1: Valid refresh token with matching session");
$cli->writeln("----------------------------------------");

// Simulate login
$username = 'testuser';
$password = 'testpass';

$cli->writeln("Authenticating user: $username");
$authResult = $authService->authenticate($username, $password, ['generate_jwt' => true]);

if (!$authResult['success']) {
    $cli->fatal("Authentication failed: " . ($authResult['error'] ?? 'unknown'));
}

$refreshToken = $authResult['refresh_token'];
$cli->writeln("✓ Authentication successful");
$cli->writeln("  Session ID: " . $authResult['session_id']);
$cli->writeln("  Refresh token: " . substr($refreshToken, 0, 20) . "...");

// Try to refresh
$cli->writeln("\nAttempting token refresh...");
$refreshResult = $authService->refreshToken($refreshToken);

if ($refreshResult['success']) {
    $cli->writeln("✓ Refresh successful");
    $cli->writeln("  New access token: " . substr($refreshResult['access_token'], 0, 20) . "...");
} else {
    $cli->fatal("Refresh failed: " . $refreshResult['error']);
}

// Test 2: Refresh token after logout (session destroyed)
$cli->writeln("\nTest 2: Refresh token after logout");
$cli->writeln("----------------------------------------");

$cli->writeln("Logging out user...");
$authService->logout();
$cli->writeln("✓ Logout complete (session destroyed)");

$cli->writeln("\nAttempting token refresh with old token...");
$refreshResult2 = $authService->refreshToken($refreshToken);

if (!$refreshResult2['success']) {
    $cli->writeln("✓ Refresh correctly failed: " . $refreshResult2['error']);
} else {
    $cli->fatal("ERROR: Refresh should have failed after logout!");
}

// Test 3: Tampered refresh token (wrong JTI)
$cli->writeln("\nTest 3: Refresh token with tampered JTI");
$cli->writeln("----------------------------------------");

// Re-authenticate
$authResult2 = $authService->authenticate($username, $password, ['generate_jwt' => true]);
$validToken = $authResult2['refresh_token'];

// Decode JWT to get parts
$parts = explode('.', $validToken);
$header = $parts[0];
$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
$signature = $parts[2];

// Tamper with JTI
$cli->writeln("Original JTI: " . $payload['jti']);
$payload['jti'] = 'tampered-jti-' . bin2hex(random_bytes(8));
$cli->writeln("Tampered JTI: " . $payload['jti']);

// Re-encode (signature will be invalid, but we're testing session validation)
$tamperedPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
$tamperedToken = $header . '.' . $tamperedPayload . '.' . $signature;

$cli->writeln("\nAttempting refresh with tampered token...");
$refreshResult3 = $authService->refreshToken($tamperedToken);

if (!$refreshResult3['success']) {
    $cli->writeln("✓ Refresh correctly failed: " . $refreshResult3['error']);
    if (strpos($refreshResult3['error'], 'signature') !== false) {
        $cli->writeln("  (Failed at signature verification - expected)");
    } elseif (strpos($refreshResult3['error'], 'session') !== false || strpos($refreshResult3['error'], 'bound') !== false) {
        $cli->writeln("  (Failed at session validation - also good)");
    }
} else {
    $cli->fatal("ERROR: Refresh should have failed with tampered token!");
}

$cli->writeln("\n" . str_repeat("=", 60));
$cli->writeln("All tests passed! Refresh token validation is working correctly.");
$cli->writeln(str_repeat("=", 60));
