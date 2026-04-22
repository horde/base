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

use Horde\Db\Adapter\Pdo\Sqlite;
use Horde\Horde\Service\SqlOAuthScopeRepository;
use Horde\OAuth\Server\Entity\Client;
use Horde\OAuth\Server\Entity\Scope;
use Horde\OAuth\Server\Repository\ScopeRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlOAuthScopeRepository::class)]
final class SqlOAuthScopeRepositoryTest extends TestCase
{
    private Sqlite $db;
    private SqlOAuthScopeRepository $repository;

    protected function setUp(): void
    {
        $this->db = new Sqlite(['dbname' => ':memory:']);
        $this->db->execute('
            CREATE TABLE horde_oauth_scopes (
                identifier VARCHAR(255) NOT NULL PRIMARY KEY
            )
        ');
        $this->repository = new SqlOAuthScopeRepository($this->db);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(ScopeRepository::class, $this->repository);
    }

    public function testFindByIdentifierFound(): void
    {
        $this->db->insert(
            'INSERT INTO horde_oauth_scopes (identifier) VALUES (?)',
            ['openid']
        );

        $scope = $this->repository->findByIdentifier('openid');

        self::assertNotNull($scope);
        self::assertSame('openid', $scope->identifier);
    }

    public function testFindByIdentifierNotFound(): void
    {
        self::assertNull($this->repository->findByIdentifier('nonexistent'));
    }

    public function testFinalizeScopesReturnsClientDefaultsWhenEmpty(): void
    {
        $client = $this->makeClient('openid email');

        $result = $this->repository->finalizeScopes([], 'authorization_code', $client);

        self::assertCount(2, $result);
        self::assertSame('openid', $result[0]->identifier);
        self::assertSame('email', $result[1]->identifier);
    }

    public function testFinalizeScopesFiltersByKnownScopes(): void
    {
        $this->db->insert('INSERT INTO horde_oauth_scopes (identifier) VALUES (?)', ['openid']);
        $this->db->insert('INSERT INTO horde_oauth_scopes (identifier) VALUES (?)', ['email']);

        $client = $this->makeClient('openid email');
        $requested = [new Scope('openid'), new Scope('email'), new Scope('admin')];

        $result = $this->repository->finalizeScopes($requested, 'authorization_code', $client);

        self::assertCount(2, $result);
        $identifiers = array_map(fn(Scope $s) => $s->identifier, $result);
        self::assertContains('openid', $identifiers);
        self::assertContains('email', $identifiers);
        self::assertNotContains('admin', $identifiers);
    }

    public function testFinalizeScopesReturnsEmptyWhenNoneKnown(): void
    {
        $client = $this->makeClient('openid');
        $requested = [new Scope('admin'), new Scope('superuser')];

        $result = $this->repository->finalizeScopes($requested, 'authorization_code', $client);

        self::assertSame([], $result);
    }

    private function makeClient(string $scope): Client
    {
        return new Client(
            clientId: 'app-test',
            clientSecretHash: null,
            clientName: 'Test',
            redirectUris: ['https://example.com/callback'],
            grantTypes: ['authorization_code'],
            scope: $scope,
            clientType: 'public',
        );
    }
}
