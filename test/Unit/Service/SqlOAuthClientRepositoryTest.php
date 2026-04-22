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

use DateTimeImmutable;
use Horde\Db\Adapter\Pdo\Sqlite;
use Horde\Horde\Service\SqlOAuthClientRepository;
use Horde\OAuth\Server\Entity\Client;
use Horde\OAuth\Server\Repository\ClientRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlOAuthClientRepository::class)]
final class SqlOAuthClientRepositoryTest extends TestCase
{
    private Sqlite $db;
    private SqlOAuthClientRepository $repository;

    protected function setUp(): void
    {
        $this->db = new Sqlite(['dbname' => ':memory:']);
        $this->db->execute('
            CREATE TABLE horde_oauth_clients (
                client_id VARCHAR(255) NOT NULL PRIMARY KEY,
                client_secret_hash VARCHAR(255),
                client_name VARCHAR(255) NOT NULL,
                redirect_uris TEXT NOT NULL,
                grant_types TEXT NOT NULL,
                scope TEXT NOT NULL,
                client_type VARCHAR(20) NOT NULL,
                token_endpoint_auth_method VARCHAR(50) NOT NULL DEFAULT \'client_secret_basic\',
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            )
        ');
        $this->repository = new SqlOAuthClientRepository($this->db);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(ClientRepository::class, $this->repository);
    }

    public function testFindByIdFound(): void
    {
        $this->insertClient('app-1', 'confidential');

        $client = $this->repository->findById('app-1');

        self::assertNotNull($client);
        self::assertSame('app-1', $client->clientId);
        self::assertSame('Test App', $client->clientName);
        self::assertSame('confidential', $client->clientType);
        self::assertSame('openid email', $client->scope);
        self::assertSame('client_secret_basic', $client->tokenEndpointAuthMethod);
    }

    public function testFindByIdNotFound(): void
    {
        self::assertNull($this->repository->findById('nonexistent'));
    }

    public function testJsonRoundtripRedirectUrisAndGrantTypes(): void
    {
        $redirectUris = ['https://app.example.com/callback', 'https://app.example.com/alt'];
        $grantTypes = ['authorization_code', 'refresh_token', 'client_credentials'];

        $this->db->insert(
            'INSERT INTO horde_oauth_clients'
            . ' (client_id, client_secret_hash, client_name, redirect_uris, grant_types,'
            . ' scope, client_type, token_endpoint_auth_method, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'app-json',
                null,
                'JSON App',
                json_encode($redirectUris),
                json_encode($grantTypes),
                'openid',
                'public',
                'none',
                1000000,
                1000000,
            ]
        );

        $client = $this->repository->findById('app-json');

        self::assertSame($redirectUris, $client->redirectUris);
        self::assertSame($grantTypes, $client->grantTypes);
    }

    public function testValidateClientConfidentialValid(): void
    {
        $this->insertClient('app-1', 'confidential');

        self::assertTrue($this->repository->validateClient('app-1', 'secret123', 'authorization_code'));
    }

    public function testValidateClientRejectsWrongSecret(): void
    {
        $this->insertClient('app-1', 'confidential');

        self::assertFalse($this->repository->validateClient('app-1', 'wrong', 'authorization_code'));
    }

    public function testValidateClientRejectsNullSecretForConfidential(): void
    {
        $this->insertClient('app-1', 'confidential');

        self::assertFalse($this->repository->validateClient('app-1', null, 'authorization_code'));
    }

    public function testValidateClientRejectsUnsupportedGrantType(): void
    {
        $this->insertClient('app-1', 'confidential');

        self::assertFalse($this->repository->validateClient('app-1', 'secret123', 'client_credentials'));
    }

    public function testValidateClientPublicWithoutSecret(): void
    {
        $this->insertClient('app-pub', 'public');

        self::assertTrue($this->repository->validateClient('app-pub', null, 'authorization_code'));
    }

    public function testValidateClientNonexistent(): void
    {
        self::assertFalse($this->repository->validateClient('missing', null, 'authorization_code'));
    }

    public function testTimestampsRoundtrip(): void
    {
        $this->insertClient('app-ts', 'public');

        $client = $this->repository->findById('app-ts');

        self::assertSame(1000000, $client->createdAt->getTimestamp());
        self::assertSame(1000050, $client->updatedAt->getTimestamp());
    }

    public function testNullSecretHash(): void
    {
        $this->db->insert(
            'INSERT INTO horde_oauth_clients'
            . ' (client_id, client_secret_hash, client_name, redirect_uris, grant_types,'
            . ' scope, client_type, token_endpoint_auth_method, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'app-nosecret',
                null,
                'Public App',
                json_encode(['https://app.example.com/callback']),
                json_encode(['authorization_code']),
                'openid',
                'public',
                'none',
                1000000,
                1000000,
            ]
        );

        $client = $this->repository->findById('app-nosecret');

        self::assertNull($client->clientSecretHash);
        self::assertTrue($client->isPublic());
    }

    private function insertClient(string $clientId, string $clientType): void
    {
        $this->db->insert(
            'INSERT INTO horde_oauth_clients'
            . ' (client_id, client_secret_hash, client_name, redirect_uris, grant_types,'
            . ' scope, client_type, token_endpoint_auth_method, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $clientId,
                password_hash('secret123', PASSWORD_BCRYPT),
                'Test App',
                json_encode(['https://app.example.com/callback']),
                json_encode(['authorization_code', 'refresh_token']),
                'openid email',
                $clientType,
                'client_secret_basic',
                1000000,
                1000050,
            ]
        );
    }
}
