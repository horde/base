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
use Horde\Horde\Service\SqlOAuthAccessTokenRepository;
use Horde\OAuth\Server\Entity\AccessToken;
use Horde\OAuth\Server\Repository\AccessTokenRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlOAuthAccessTokenRepository::class)]
final class SqlOAuthAccessTokenRepositoryTest extends TestCase
{
    private Sqlite $db;
    private SqlOAuthAccessTokenRepository $repository;

    protected function setUp(): void
    {
        $this->db = new Sqlite(['dbname' => ':memory:']);
        $this->db->execute('
            CREATE TABLE horde_oauth_access_tokens (
                token_id VARCHAR(255) NOT NULL PRIMARY KEY,
                client_id VARCHAR(255) NOT NULL,
                identity_id VARCHAR(255),
                scope TEXT NOT NULL,
                expires_at INTEGER NOT NULL,
                revoked INTEGER NOT NULL DEFAULT 0
            )
        ');
        $this->repository = new SqlOAuthAccessTokenRepository($this->db);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(AccessTokenRepository::class, $this->repository);
    }

    public function testPersistAndFindById(): void
    {
        $token = new AccessToken(
            tokenId: 'tok-1',
            clientId: 'app-1',
            identityId: 'id-1',
            scope: 'openid email',
            expiresAt: new DateTimeImmutable('@2000000'),
        );
        $this->repository->persist($token);

        $loaded = $this->repository->findById('tok-1');

        self::assertNotNull($loaded);
        self::assertSame('tok-1', $loaded->tokenId);
        self::assertSame('app-1', $loaded->clientId);
        self::assertSame('id-1', $loaded->identityId);
        self::assertSame('openid email', $loaded->scope);
        self::assertSame(2000000, $loaded->expiresAt->getTimestamp());
        self::assertFalse($loaded->revoked);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        self::assertNull($this->repository->findById('nonexistent'));
    }

    public function testNullIdentityId(): void
    {
        $token = new AccessToken(
            tokenId: 'tok-cc',
            clientId: 'app-1',
            identityId: null,
            scope: 'api',
            expiresAt: new DateTimeImmutable('@2000000'),
        );
        $this->repository->persist($token);

        $loaded = $this->repository->findById('tok-cc');

        self::assertNull($loaded->identityId);
    }

    public function testRevokeAndIsRevoked(): void
    {
        $token = new AccessToken(
            tokenId: 'tok-rev',
            clientId: 'app-1',
            identityId: 'id-1',
            scope: 'openid',
            expiresAt: new DateTimeImmutable('@2000000'),
        );
        $this->repository->persist($token);

        self::assertFalse($this->repository->isRevoked('tok-rev'));

        $this->repository->revoke('tok-rev');

        self::assertTrue($this->repository->isRevoked('tok-rev'));

        $loaded = $this->repository->findById('tok-rev');
        self::assertTrue($loaded->revoked);
    }

    public function testIsRevokedReturnsFalseForUnrevoked(): void
    {
        $token = new AccessToken(
            tokenId: 'tok-ok',
            clientId: 'app-1',
            identityId: 'id-1',
            scope: 'openid',
            expiresAt: new DateTimeImmutable('@2000000'),
        );
        $this->repository->persist($token);

        self::assertFalse($this->repository->isRevoked('tok-ok'));
    }
}
