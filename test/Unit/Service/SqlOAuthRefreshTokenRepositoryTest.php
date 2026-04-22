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
use Horde\Horde\Service\SqlOAuthRefreshTokenRepository;
use Horde\OAuth\Server\Entity\RefreshToken;
use Horde\OAuth\Server\Repository\RefreshTokenRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlOAuthRefreshTokenRepository::class)]
final class SqlOAuthRefreshTokenRepositoryTest extends TestCase
{
    private Sqlite $db;
    private SqlOAuthRefreshTokenRepository $repository;

    protected function setUp(): void
    {
        $this->db = new Sqlite(['dbname' => ':memory:']);
        $this->db->execute('
            CREATE TABLE horde_oauth_refresh_tokens (
                token_id VARCHAR(255) NOT NULL PRIMARY KEY,
                access_token_id VARCHAR(255) NOT NULL,
                client_id VARCHAR(255) NOT NULL,
                identity_id VARCHAR(255),
                scope TEXT NOT NULL,
                expires_at INTEGER NOT NULL,
                revoked INTEGER NOT NULL DEFAULT 0
            )
        ');
        $this->repository = new SqlOAuthRefreshTokenRepository($this->db);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(RefreshTokenRepository::class, $this->repository);
    }

    public function testPersistAndFindById(): void
    {
        $token = new RefreshToken(
            tokenId: 'rt-1',
            accessTokenId: 'at-1',
            clientId: 'app-1',
            identityId: 'id-1',
            scope: 'openid email',
            expiresAt: new DateTimeImmutable('@3000000'),
        );
        $this->repository->persist($token);

        $loaded = $this->repository->findById('rt-1');

        self::assertNotNull($loaded);
        self::assertSame('rt-1', $loaded->tokenId);
        self::assertSame('at-1', $loaded->accessTokenId);
        self::assertSame('app-1', $loaded->clientId);
        self::assertSame('id-1', $loaded->identityId);
        self::assertSame('openid email', $loaded->scope);
        self::assertSame(3000000, $loaded->expiresAt->getTimestamp());
        self::assertFalse($loaded->revoked);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        self::assertNull($this->repository->findById('nonexistent'));
    }

    public function testRevokeAndIsRevoked(): void
    {
        $this->persistToken('rt-rev', 'at-1');

        self::assertFalse($this->repository->isRevoked('rt-rev'));

        $this->repository->revoke('rt-rev');

        self::assertTrue($this->repository->isRevoked('rt-rev'));
    }

    public function testRevokeByAccessTokenId(): void
    {
        $this->persistToken('rt-a', 'at-shared');
        $this->persistToken('rt-b', 'at-shared');
        $this->persistToken('rt-c', 'at-other');

        $this->repository->revokeByAccessTokenId('at-shared');

        self::assertTrue($this->repository->isRevoked('rt-a'));
        self::assertTrue($this->repository->isRevoked('rt-b'));
        self::assertFalse($this->repository->isRevoked('rt-c'));
    }

    public function testNullIdentityId(): void
    {
        $token = new RefreshToken(
            tokenId: 'rt-null',
            accessTokenId: 'at-1',
            clientId: 'app-1',
            identityId: null,
            scope: 'api',
            expiresAt: new DateTimeImmutable('@3000000'),
        );
        $this->repository->persist($token);

        $loaded = $this->repository->findById('rt-null');

        self::assertNull($loaded->identityId);
    }

    private function persistToken(string $tokenId, string $accessTokenId): void
    {
        $this->repository->persist(new RefreshToken(
            tokenId: $tokenId,
            accessTokenId: $accessTokenId,
            clientId: 'app-1',
            identityId: 'id-1',
            scope: 'openid',
            expiresAt: new DateTimeImmutable('@3000000'),
        ));
    }
}
