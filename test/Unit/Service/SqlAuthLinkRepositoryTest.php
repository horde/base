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
use Horde\Horde\Service\AuthLink;
use Horde\Horde\Service\AuthLinkRepository;
use Horde\Horde\Service\SqlAuthLinkRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Exception;

#[CoversClass(SqlAuthLinkRepository::class)]
final class SqlAuthLinkRepositoryTest extends TestCase
{
    private Sqlite $db;
    private SqlAuthLinkRepository $repository;

    protected function setUp(): void
    {
        $this->db = new Sqlite(['dbname' => ':memory:']);
        $this->db->execute('
            CREATE TABLE horde_identity_auth_links (
                link_id INTEGER PRIMARY KEY AUTOINCREMENT,
                identity_id VARCHAR(255) NOT NULL,
                provider VARCHAR(255) NOT NULL,
                external_id VARCHAR(255) NOT NULL,
                external_email VARCHAR(255),
                external_display_name VARCHAR(255),
                linked_at INTEGER NOT NULL,
                last_used_at INTEGER,
                metadata TEXT,
                UNIQUE (provider, external_id)
            )
        ');
        $this->repository = new SqlAuthLinkRepository($this->db);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(AuthLinkRepository::class, $this->repository);
    }

    public function testSaveNewReturnsPopulatedLinkId(): void
    {
        $link = $this->makeLink();
        $saved = $this->repository->save($link);

        self::assertGreaterThan(0, $saved->linkId);
        self::assertSame('id-1', $saved->identityId);
        self::assertSame('local:user', $saved->provider);
        self::assertSame('alice', $saved->externalId);
    }

    public function testResolveFound(): void
    {
        $this->repository->save($this->makeLink());

        $found = $this->repository->resolve('local:user', 'alice');

        self::assertNotNull($found);
        self::assertSame('id-1', $found->identityId);
        self::assertSame('local:user', $found->provider);
        self::assertSame('alice', $found->externalId);
    }

    public function testResolveNotFound(): void
    {
        self::assertNull($this->repository->resolve('local:user', 'nobody'));
    }

    public function testFindByIdentityMultipleLinks(): void
    {
        $this->repository->save($this->makeLink());
        $this->repository->save(new AuthLink(
            linkId: 0,
            identityId: 'id-1',
            provider: 'google',
            externalId: 'google-sub-123',
            externalEmail: 'alice@gmail.com',
            externalDisplayName: 'Alice G',
            linkedAt: new DateTimeImmutable('@1000100'),
            lastUsedAt: null,
            metadata: null,
        ));

        $links = $this->repository->findByIdentity('id-1');

        self::assertCount(2, $links);
        self::assertSame('local:user', $links[0]->provider);
        self::assertSame('google', $links[1]->provider);
    }

    public function testFindByIdentityEmptyForUnknown(): void
    {
        self::assertSame([], $this->repository->findByIdentity('nonexistent'));
    }

    public function testSaveExistingUpdates(): void
    {
        $saved = $this->repository->save($this->makeLink());

        $updated = new AuthLink(
            linkId: $saved->linkId,
            identityId: $saved->identityId,
            provider: $saved->provider,
            externalId: $saved->externalId,
            externalEmail: 'newalice@example.com',
            externalDisplayName: 'Alice Updated',
            linkedAt: $saved->linkedAt,
            lastUsedAt: new DateTimeImmutable('@1000200'),
            metadata: ['note' => 'updated'],
        );
        $this->repository->save($updated);

        $loaded = $this->repository->resolve('local:user', 'alice');
        self::assertSame('newalice@example.com', $loaded->externalEmail);
        self::assertSame('Alice Updated', $loaded->externalDisplayName);
        self::assertSame(1000200, $loaded->lastUsedAt->getTimestamp());
        self::assertSame(['note' => 'updated'], $loaded->metadata);
    }

    public function testDeleteByLinkId(): void
    {
        $saved = $this->repository->save($this->makeLink());

        $this->repository->delete($saved->linkId);

        self::assertNull($this->repository->resolve('local:user', 'alice'));
    }

    public function testDeleteByProviderAndExternalId(): void
    {
        $this->repository->save($this->makeLink());

        $this->repository->deleteByProviderAndExternalId('local:user', 'alice');

        self::assertNull($this->repository->resolve('local:user', 'alice'));
    }

    public function testUpdateLastUsed(): void
    {
        $saved = $this->repository->save($this->makeLink());
        self::assertNull($saved->lastUsedAt);

        $at = new DateTimeImmutable('@2000000');
        $this->repository->updateLastUsed($saved->linkId, $at);

        $loaded = $this->repository->resolve('local:user', 'alice');
        self::assertSame(2000000, $loaded->lastUsedAt->getTimestamp());
    }

    public function testUniqueConstraintOnProviderAndExternalId(): void
    {
        $this->repository->save($this->makeLink());

        $this->expectException(Exception::class);
        $this->repository->save(new AuthLink(
            linkId: 0,
            identityId: 'id-2',
            provider: 'local:user',
            externalId: 'alice',
            externalEmail: null,
            externalDisplayName: null,
            linkedAt: new DateTimeImmutable('@1000000'),
            lastUsedAt: null,
            metadata: null,
        ));
    }

    public function testMetadataJsonRoundtrip(): void
    {
        $metadata = [
            'scopes' => ['openid', 'email', 'profile'],
            'issuer' => 'https://accounts.google.com',
            'nested' => ['key' => 'value'],
        ];
        $link = new AuthLink(
            linkId: 0,
            identityId: 'id-1',
            provider: 'google',
            externalId: 'google-sub-456',
            externalEmail: 'alice@gmail.com',
            externalDisplayName: 'Alice',
            linkedAt: new DateTimeImmutable('@1000000'),
            lastUsedAt: null,
            metadata: $metadata,
        );

        $saved = $this->repository->save($link);
        $loaded = $this->repository->resolve('google', 'google-sub-456');

        self::assertSame($metadata, $loaded->metadata);
    }

    public function testNullMetadataRoundtrip(): void
    {
        $saved = $this->repository->save($this->makeLink());
        $loaded = $this->repository->resolve('local:user', 'alice');

        self::assertNull($loaded->metadata);
    }

    public function testNullOptionalFieldsRoundtrip(): void
    {
        $saved = $this->repository->save($this->makeLink());
        $loaded = $this->repository->resolve('local:user', 'alice');

        self::assertNull($loaded->externalEmail);
        self::assertNull($loaded->externalDisplayName);
        self::assertNull($loaded->lastUsedAt);
    }

    public function testDifferentIdentitiesAreSeparated(): void
    {
        $this->repository->save($this->makeLink());
        $this->repository->save(new AuthLink(
            linkId: 0,
            identityId: 'id-2',
            provider: 'local:user',
            externalId: 'bob',
            externalEmail: null,
            externalDisplayName: null,
            linkedAt: new DateTimeImmutable('@1000000'),
            lastUsedAt: null,
            metadata: null,
        ));

        self::assertCount(1, $this->repository->findByIdentity('id-1'));
        self::assertCount(1, $this->repository->findByIdentity('id-2'));
    }

    private function makeLink(): AuthLink
    {
        return new AuthLink(
            linkId: 0,
            identityId: 'id-1',
            provider: AuthLink::PROVIDER_LOCAL,
            externalId: 'alice',
            externalEmail: null,
            externalDisplayName: null,
            linkedAt: new DateTimeImmutable('@1000000'),
            lastUsedAt: null,
            metadata: null,
        );
    }
}
