<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Integration;

use PHPUnit\Framework\TestCase;
use Horde\Horde\Service\AuthenticationService;
use Horde\Horde\Service\JwtService;

/**
 * Integration Test: Authentication Service with Full Horde Environment
 *
 * Tests that require complete Horde setup including Horde_Auth, injector,
 * and full authentication backend.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @coversNothing
 */
class AuthenticationServiceFullEnvironmentTest extends TestCase
{
    private $authService;
    private $registry;
    private $jwtService;
    private $injector;

    protected function setUp(): void
    {
        // Skip if not in full Horde environment
        if (!defined('HORDE_BASE')) {
            $this->markTestSkipped('Requires Horde environment');
        }

        $this->injector = $GLOBALS['injector'] ?? null;
        if (!$this->injector) {
            $this->markTestSkipped('Requires Horde injector');
        }

        $this->registry = $this->injector->getInstance('Horde_Registry');
        $this->authService = $this->injector->getInstance(AuthenticationService::class);
        $this->jwtService = $this->injector->getInstance(JwtService::class);
    }

    /**
     * Test that authenticate() uses JTI as session ID when JWT enabled
     *
     * This test validates that when a user authenticates with JWT generation
     * enabled, the session ID is set to the refresh token's JTI.
     */
    public function testAuthenticateUsesJtiAsSessionId(): void
    {
        // Clean slate
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_start();

        echo "\n=== Test: authenticate() uses JTI as session ID ===\n";

        // Authenticate with JWT generation
        echo "1. Authenticating user with JWT generation...\n";
        $result = $this->authService->authenticate('testuser', 'testpass', [
            'generate_jwt' => true,
        ]);

        $this->assertTrue($result['success'], 'Authentication should succeed');
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('access_token', $result);

        // Extract JTI from refresh token
        $refreshToken = $result['refresh_token'];
        $jti = $this->extractJti($refreshToken);

        echo "2. Refresh token JTI: $jti\n";

        // Verify session ID matches JTI
        $actualSessionId = session_id();
        echo "3. Actual session ID: $actualSessionId\n";

        $this->assertEquals(
            $jti,
            $actualSessionId,
            'Session ID should equal refresh token JTI'
        );

        echo "4. ✅ Session ID matches JTI\n";
        echo "   This means the JTI IS the session identifier\n";

        // Clean up
        session_destroy();
    }

    /**
     * Test that authenticate() registers refresh token in session registry
     *
     * Validates that successful authentication with JWT creates an entry
     * in the session registry with token metadata.
     */
    public function testAuthenticateRegistersRefreshToken(): void
    {
        // Setup session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];

        echo "\n=== Test: authenticate() registers refresh token ===\n";

        // Authenticate with JWT generation
        echo "1. Authenticating user with JWT generation...\n";
        $result = $this->authService->authenticate('testuser', 'testpass', [
            'generate_jwt' => true,
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('refresh_token', $result);

        // Extract JTI
        $jti = $this->extractJti($result['refresh_token']);
        echo "2. Refresh token JTI: $jti\n";

        // Verify refresh token registered in session
        $this->assertArrayHasKey('__horde', $_SESSION);
        $this->assertArrayHasKey('auth', $_SESSION['__horde']);
        $this->assertArrayHasKey('refresh_tokens', $_SESSION['__horde']['auth']);

        $registry = $_SESSION['__horde']['auth']['refresh_tokens'];
        $this->assertArrayHasKey($jti, $registry);

        echo "3. ✅ Token found in session registry\n";

        // Verify metadata structure
        $metadata = $registry[$jti];
        $this->assertArrayHasKey('issued_at', $metadata);
        $this->assertArrayHasKey('expires_at', $metadata);
        $this->assertArrayHasKey('last_used', $metadata);
        $this->assertArrayHasKey('user_agent', $metadata);

        echo "4. ✅ Metadata structure correct:\n";
        echo "   - issued_at: {$metadata['issued_at']}\n";
        echo "   - expires_at: {$metadata['expires_at']}\n";
        echo "   - last_used: {$metadata['last_used']}\n";
        echo "   - user_agent: {$metadata['user_agent']}\n";

        // Clean up
        session_destroy();
    }

    // Helper methods

    private function extractJti(string $jwt): string
    {
        // Parse JWT to extract JTI claim
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid JWT format');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        return $payload['jti'] ?? 'unknown';
    }
}
