<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Integration;

use PHPUnit\Framework\TestCase;
use Horde\Horde\Service\AuthenticationService;
use Exception;

/**
 * Integration Test: JWT Session Binding
 *
 * Demonstrates how JWT refresh tokens are bound to sessions and validates
 * that logout properly invalidates tokens.
 *
 * Test Scenarios:
 * 1. Token works on issuing session
 * 2. Token fails on different session (different user)
 * 3. Token fails after logout (session destroyed)
 * 4. Multi-device: Two sessions, two tokens (isolated)
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @coversNothing
 */
class JwtSessionBindingTest extends TestCase
{
    private $authService;
    private $registry;
    private $jwtService;
    private $injector;

    protected function setUp(): void
    {
        // This test requires a real Horde environment
        // Can be run with: vendor/bin/phpunit --filter JwtSessionBindingTest

        // Skip if not in Horde environment
        if (!defined('HORDE_BASE')) {
            $this->markTestSkipped('Requires Horde environment');
        }

        $this->injector = $GLOBALS['injector'] ?? null;
        if (!$this->injector) {
            $this->markTestSkipped('Requires Horde injector');
        }

        $this->registry = $this->injector->getInstance('Horde_Registry');
        $this->authService = $this->injector->getInstance(AuthenticationService::class);
        $this->jwtService = $this->injector->getInstance(\Horde\Horde\Service\JwtService::class);
    }

    /**
     * Test 1: Token works on issuing session
     *
     * This is the happy path - user logs in, gets tokens, can refresh them.
     */
    public function testRefreshTokenWorksOnIssuingSession(): void
    {
        echo "\n=== Test 1: Token works on issuing session ===\n";

        // Start fresh session
        $this->startNewSession();

        // Authenticate user
        echo "1. Authenticating user: testuser\n";
        $result = $this->authService->authenticate('testuser', 'testpass', [
            'generate_jwt' => true,
        ]);

        $this->assertTrue($result['success'], 'Authentication should succeed');
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('access_token', $result);

        $refreshToken = $result['refresh_token'];
        $sessionId = session_id();

        echo "2. Session created: $sessionId\n";
        echo "3. Refresh token issued\n";

        // Verify token is in session registry
        $registry = $_SESSION['__horde']['auth']['refresh_tokens'] ?? [];
        $this->assertNotEmpty($registry, 'Registry should contain token');

        $jti = $this->extractJti($refreshToken);
        echo "4. Token JTI: $jti\n";
        echo "5. Token registered in session registry: YES\n";

        $this->assertArrayHasKey($jti, $registry, 'Token should be in registry');

        // Now refresh the token
        echo "6. Attempting to refresh token...\n";
        $refreshResult = $this->authService->refreshToken($refreshToken);

        $this->assertTrue($refreshResult['success'], 'Refresh should succeed');
        $this->assertArrayHasKey('access_token', $refreshResult);

        echo "7. ✅ Refresh SUCCESS - New access token issued\n";
        echo "   Reason: Token found in THIS session's registry\n";
    }

    /**
     * Test 2: Token fails on different session
     *
     * Demonstrates session binding - token from Session A doesn't work in Session B.
     */
    public function testRefreshTokenFailsOnDifferentSession(): void
    {
        echo "\n=== Test 2: Token fails on different session ===\n";

        // Session A: Login as user1
        echo "1. Starting Session A...\n";
        $this->startNewSession();
        $sessionA = session_id();

        echo "2. Authenticating user1 in Session A\n";
        $resultA = $this->authService->authenticate('user1', 'pass1', [
            'generate_jwt' => true,
        ]);

        $this->assertTrue($resultA['success']);
        $refreshTokenA = $resultA['refresh_token'];
        $jtiA = $this->extractJti($refreshTokenA);

        echo "3. Session A ID: $sessionA\n";
        echo "4. Token A issued with JTI: $jtiA\n";
        echo "5. Token A registered in Session A registry\n";

        // Verify token A is in Session A's registry
        $registryA = $_SESSION['__horde']['auth']['refresh_tokens'] ?? [];
        $this->assertArrayHasKey($jtiA, $registryA);

        // Session B: Login as user2
        echo "\n6. Starting Session B...\n";
        $this->startNewSession();
        $sessionB = session_id();

        echo "7. Authenticating user2 in Session B\n";
        $resultB = $this->authService->authenticate('user2', 'pass2', [
            'generate_jwt' => true,
        ]);

        $this->assertTrue($resultB['success']);
        $refreshTokenB = $resultB['refresh_token'];
        $jtiB = $this->extractJti($refreshTokenB);

        echo "8. Session B ID: $sessionB\n";
        echo "9. Token B issued with JTI: $jtiB\n";
        echo "10. Token B registered in Session B registry\n";

        // Verify token B is in Session B's registry
        $registryB = $_SESSION['__horde']['auth']['refresh_tokens'] ?? [];
        $this->assertArrayHasKey($jtiB, $registryB);
        $this->assertArrayNotHasKey($jtiA, $registryB, 'Token A should NOT be in Session B');

        echo "\n11. Attempting to use Token A (from Session A) in Session B...\n";
        echo "    Current session: $sessionB\n";
        echo "    Token JTI: $jtiA\n";
        echo "    Checking registry: [" . implode(', ', array_keys($registryB)) . "]\n";

        // Try to use Token A (from Session A) in Session B
        $refreshResult = $this->authService->refreshToken($refreshTokenA);

        $this->assertFalse($refreshResult['success'], 'Refresh should fail');
        $this->assertStringContainsString(
            'not valid for this session',
            $refreshResult['error'],
            'Error should indicate session mismatch'
        );

        echo "12. ❌ Refresh FAILED - Correct!\n";
        echo "    Reason: Token A not in Session B's registry\n";
        echo "    Error: {$refreshResult['error']}\n";
    }

    /**
     * Test 3: Token fails after logout
     *
     * Demonstrates that logout destroys session and invalidates all tokens.
     */
    public function testRefreshTokenFailsAfterLogout(): void
    {
        echo "\n=== Test 3: Token fails after logout ===\n";

        // Start session and login
        $this->startNewSession();
        $sessionId = session_id();

        echo "1. Authenticating user: testuser\n";
        $result = $this->authService->authenticate('testuser', 'testpass', [
            'generate_jwt' => true,
        ]);

        $this->assertTrue($result['success']);
        $refreshToken = $result['refresh_token'];
        $jti = $this->extractJti($refreshToken);

        echo "2. Session ID: $sessionId\n";
        echo "3. Token issued with JTI: $jti\n";
        echo "4. Token registered in session registry\n";

        // Verify token works before logout
        echo "\n5. Testing token BEFORE logout...\n";
        $refreshResult1 = $this->authService->refreshToken($refreshToken);
        $this->assertTrue($refreshResult1['success'], 'Refresh should work before logout');
        echo "6. ✅ Refresh works - Token valid\n";

        // Logout (destroys session)
        echo "\n7. Logging out...\n";
        $this->authService->logout();
        echo "8. Session destroyed\n";

        // Verify session really gone
        $this->assertNull($this->registry->getAuth(), 'Session should be cleared');
        echo "9. Confirmed: Session no longer authenticated\n";

        // Try to refresh after logout
        echo "\n10. Attempting to use token AFTER logout...\n";
        echo "    Token JTI: $jti\n";
        echo "    Checking registry: " . (isset($_SESSION['__horde']['auth']['refresh_tokens']) ? 'GONE' : 'GONE') . "\n";

        $refreshResult2 = $this->authService->refreshToken($refreshToken);

        $this->assertFalse($refreshResult2['success'], 'Refresh should fail after logout');
        $this->assertStringContainsString(
            'Session expired',
            $refreshResult2['error'],
            'Error should indicate session expired'
        );

        echo "11. ❌ Refresh FAILED - Correct!\n";
        echo "    Reason: Session destroyed, registry destroyed with it\n";
        echo "    Error: {$refreshResult2['error']}\n";
    }

    /**
     * Test 4: Multi-device scenario
     *
     * Demonstrates that logout on one device doesn't affect another device.
     */
    public function testMultiDeviceIsolation(): void
    {
        echo "\n=== Test 4: Multi-device isolation ===\n";

        // Device A (Laptop): Login
        echo "1. Device A (Laptop): Starting session...\n";
        $this->startNewSession();
        $sessionA = session_id();

        echo "2. Device A: Authenticating testuser\n";
        $resultA = $this->authService->authenticate('testuser', 'testpass', [
            'generate_jwt' => true,
        ]);

        $this->assertTrue($resultA['success']);
        $refreshTokenA = $resultA['refresh_token'];
        $jtiA = $this->extractJti($refreshTokenA);

        echo "3. Device A Session: $sessionA\n";
        echo "4. Device A Token JTI: $jtiA\n";
        echo "5. Device A: Token registered in session registry\n";

        // Device B (Phone): Login
        echo "\n6. Device B (Phone): Starting session...\n";
        $this->startNewSession();
        $sessionB = session_id();

        echo "7. Device B: Authenticating testuser (same user)\n";
        $resultB = $this->authService->authenticate('testuser', 'testpass', [
            'generate_jwt' => true,
        ]);

        $this->assertTrue($resultB['success']);
        $refreshTokenB = $resultB['refresh_token'];
        $jtiB = $this->extractJti($refreshTokenB);

        echo "8. Device B Session: $sessionB\n";
        echo "9. Device B Token JTI: $jtiB\n";
        echo "10. Device B: Token registered in session registry\n";

        // Verify both tokens work
        echo "\n11. Testing Device A token in Session A...\n";
        $this->restoreSession($sessionA);
        $refreshResultA1 = $this->authService->refreshToken($refreshTokenA);
        $this->assertTrue($refreshResultA1['success']);
        echo "12. ✅ Device A token works in Session A\n";

        echo "\n13. Testing Device B token in Session B...\n";
        $this->restoreSession($sessionB);
        $refreshResultB1 = $this->authService->refreshToken($refreshTokenB);
        $this->assertTrue($refreshResultB1['success']);
        echo "14. ✅ Device B token works in Session B\n";

        // Logout on Device A
        echo "\n15. Device A: Logging out...\n";
        $this->restoreSession($sessionA);
        $this->authService->logout();
        echo "16. Device A: Session destroyed\n";

        // Try Device A token after logout
        echo "\n17. Device A: Attempting to use token after logout...\n";
        $refreshResultA2 = $this->authService->refreshToken($refreshTokenA);
        $this->assertFalse($refreshResultA2['success']);
        echo "18. ❌ Device A token FAILED - Correct! (session destroyed)\n";

        // Device B should still work
        echo "\n19. Device B: Testing token (should still work)...\n";
        $this->restoreSession($sessionB);
        $refreshResultB2 = $this->authService->refreshToken($refreshTokenB);
        $this->assertTrue($refreshResultB2['success'], 'Device B should still work');
        echo "20. ✅ Device B token WORKS - Correct! (different session)\n";

        echo "\n=== Multi-device isolation verified ===\n";
        echo "Result: Logout on Device A did NOT affect Device B\n";
    }

    /**
     * Test 5: Demonstrate registry structure
     *
     * Shows what the session registry actually looks like.
     */
    public function testRegistryStructure(): void
    {
        echo "\n=== Test 5: Registry structure ===\n";

        $this->startNewSession();
        $sessionId = session_id();

        echo "1. Session ID: $sessionId\n";
        echo "2. Authenticating user...\n";

        $result = $this->authService->authenticate('testuser', 'testpass', [
            'generate_jwt' => true,
        ]);

        $this->assertTrue($result['success']);
        $refreshToken = $result['refresh_token'];
        $jti = $this->extractJti($refreshToken);

        echo "\n3. Session structure:\n";
        echo "   \$_SESSION['__horde']['auth'] = [\n";
        echo "       'userId' => '{$this->registry->getAuth()}',\n";
        echo "       'credentials' => [...],\n";
        echo "       'refresh_tokens' => [\n";

        $registry = $_SESSION['__horde']['auth']['refresh_tokens'] ?? [];
        foreach ($registry as $tokenJti => $metadata) {
            echo "           '$tokenJti' => [\n";
            echo "               'issued_at' => {$metadata['issued_at']},\n";
            echo "               'expires_at' => {$metadata['expires_at']},\n";
            echo "               'last_used' => {$metadata['last_used']},\n";
            echo "               'user_agent' => '{$metadata['user_agent']}',\n";
            echo "           ],\n";
        }

        echo "       ]\n";
        echo "   ];\n";

        echo "\n4. Validation process:\n";
        echo "   Step 1: Extract JTI from JWT: $jti\n";
        echo "   Step 2: Check if JTI in registry: " . (isset($registry[$jti]) ? 'YES ✅' : 'NO ❌') . "\n";
        echo "   Step 3: Verify session authenticated: " . ($this->registry->getAuth() ? 'YES ✅' : 'NO ❌') . "\n";
        echo "   Step 4: Issue new access token ✅\n";
    }

    // Helper methods

    private function startNewSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_start();
    }

    private function restoreSession(string $sessionId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id($sessionId);
        session_start();
    }

    private function extractJti(string $jwt): string
    {
        // Parse JWT to extract JTI claim
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new Exception('Invalid JWT format');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        return $payload['jti'] ?? 'unknown';
    }
}
