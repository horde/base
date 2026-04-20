<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Horde\Test\Unit\Service;

use Horde\Core\Service\Exception\OauthProviderConfigNotFoundException;
use Horde\Core\Service\OauthProviderConfigRepository;
use Horde\Db\Adapter;
use Horde\Horde\Service\SqlOauthProviderConfigRepository;
use Horde\Secret\SecretManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlOauthProviderConfigRepository::class)]
final class SqlOauthProviderConfigRepositoryTest extends TestCase
{
    private Adapter&MockObject $db;
    private SecretManager $secret;
    private SqlOauthProviderConfigRepository $repository;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Adapter::class);
        $this->secret = SecretManager::create('test-key-for-unit-tests');
        $this->repository = new SqlOauthProviderConfigRepository($this->db, $this->secret);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(OauthProviderConfigRepository::class, $this->repository);
    }

    public function testGetReturnsDecryptedConfig(): void
    {
        $clientSecret = $this->secret->encrypt('my-secret');
        $scopes = json_encode(['openid', 'email'], JSON_THROW_ON_ERROR);

        $this->db->method('selectOne')
            ->with(
                self::stringContains('SELECT * FROM'),
                ['google']
            )
            ->willReturn([
                'id' => '1',
                'provider_id' => 'google',
                'type' => 'oidc',
                'name' => 'Google',
                'issuer' => 'https://accounts.google.com',
                'enabled' => '1',
                'client_id' => 'client-123',
                'client_secret' => $clientSecret->toBase64(),
                'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_endpoint' => 'https://oauth2.googleapis.com/token',
                'userinfo_endpoint' => null,
                'jwks_uri' => null,
                'revocation_endpoint' => null,
                'introspection_endpoint' => null,
                'scopes_supported' => $scopes,
                'response_types_supported' => null,
                'grant_types_supported' => null,
                'token_endpoint_auth_methods_supported' => null,
                'id_token_signing_alg_values_supported' => null,
                'default_scopes' => 'openid email',
                'redirect_uri' => null,
                'app_identifier' => null,
                'private_key' => null,
                'installation_id' => null,
                'created_at' => '1713500000',
                'updated_at' => '1713500000',
            ]);

        $result = $this->repository->get('google');

        self::assertSame('google', $result['provider_id']);
        self::assertSame('my-secret', $result['client_secret']);
        self::assertSame(['openid', 'email'], $result['scopes_supported']);
    }

    public function testGetThrowsWhenNotFound(): void
    {
        $this->db->method('selectOne')->willReturn([]);

        $this->expectException(OauthProviderConfigNotFoundException::class);
        $this->repository->get('nonexistent');
    }

    public function testSaveInsertsNewProvider(): void
    {
        $this->db->method('selectValue')->willReturn('0');

        $this->db->expects(self::once())
            ->method('insert')
            ->with(
                self::stringContains('INSERT INTO'),
                self::callback(function (array $params): bool {
                    return in_array('google', $params, true)
                        && in_array('oidc', $params, true);
                })
            );

        $this->repository->save('google', [
            'type' => 'oidc',
            'name' => 'Google',
            'client_id' => 'client-123',
            'client_secret' => 'my-secret',
        ]);
    }

    public function testSaveUpdatesExistingProvider(): void
    {
        $this->db->method('selectValue')->willReturn('1');

        $this->db->expects(self::once())
            ->method('update')
            ->with(
                self::stringContains('UPDATE'),
                self::callback(function (array $params): bool {
                    return end($params) === 'google';
                })
            );

        $this->db->expects(self::never())->method('insert');

        $this->repository->save('google', [
            'name' => 'Google Updated',
        ]);
    }

    public function testListAllReturnsAllProviders(): void
    {
        $this->db->method('selectAll')
            ->with(self::stringContains('ORDER BY name'))
            ->willReturn([
                [
                    'id' => '1',
                    'provider_id' => 'google',
                    'type' => 'oidc',
                    'name' => 'Google',
                    'enabled' => '1',
                    'client_secret' => null,
                    'private_key' => null,
                    'scopes_supported' => null,
                    'response_types_supported' => null,
                    'grant_types_supported' => null,
                    'token_endpoint_auth_methods_supported' => null,
                    'id_token_signing_alg_values_supported' => null,
                    'created_at' => '1713500000',
                    'updated_at' => '1713500000',
                ],
            ]);

        $result = $this->repository->listAll();
        self::assertCount(1, $result);
        self::assertSame('google', $result[0]['provider_id']);
    }

    public function testListEnabledFiltersDisabled(): void
    {
        $this->db->method('selectAll')
            ->with(self::stringContains('WHERE enabled = 1'))
            ->willReturn([]);

        $result = $this->repository->listEnabled();
        self::assertSame([], $result);
    }

    public function testExistsReturnsTrueWhenFound(): void
    {
        $this->db->method('selectValue')
            ->with(
                self::stringContains('SELECT COUNT'),
                ['google']
            )
            ->willReturn('1');

        self::assertTrue($this->repository->exists('google'));
    }

    public function testExistsReturnsFalseWhenNotFound(): void
    {
        $this->db->method('selectValue')->willReturn('0');

        self::assertFalse($this->repository->exists('nonexistent'));
    }

    public function testDeleteCallsDbDelete(): void
    {
        $this->db->expects(self::once())
            ->method('delete')
            ->with(
                self::stringContains('DELETE FROM'),
                ['google']
            );

        $this->repository->delete('google');
    }

    public function testSaveEncryptsSensitiveFields(): void
    {
        $this->db->method('selectValue')->willReturn('0');

        $capturedParams = null;
        $this->db->expects(self::once())
            ->method('insert')
            ->with(
                self::anything(),
                self::callback(function (array $params) use (&$capturedParams): bool {
                    $capturedParams = $params;
                    return true;
                })
            );

        $this->repository->save('github-app', [
            'type' => 'service_app',
            'name' => 'GitHub App',
            'private_key' => '-----BEGIN RSA PRIVATE KEY-----',
            'app_identifier' => '12345',
            'installation_id' => '67890',
        ]);

        $found = false;
        foreach ($capturedParams as $param) {
            if (is_string($param) && $param !== '-----BEGIN RSA PRIVATE KEY-----' && strlen($param) > 20) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'private_key should be encrypted, not stored as plaintext');
    }

    public function testSaveEncodesJsonArrayFields(): void
    {
        $this->db->method('selectValue')->willReturn('0');

        $capturedParams = null;
        $capturedSql = null;
        $this->db->expects(self::once())
            ->method('insert')
            ->with(
                self::callback(function (string $sql) use (&$capturedSql): bool {
                    $capturedSql = $sql;
                    return true;
                }),
                self::callback(function (array $params) use (&$capturedParams): bool {
                    $capturedParams = $params;
                    return true;
                })
            );

        $this->repository->save('test', [
            'type' => 'oidc',
            'name' => 'Test',
            'scopes_supported' => ['openid', 'email'],
        ]);

        $jsonFound = false;
        foreach ($capturedParams as $param) {
            if ($param === '["openid","email"]') {
                $jsonFound = true;
                break;
            }
        }
        self::assertTrue($jsonFound, 'scopes_supported should be JSON-encoded');
    }
}
