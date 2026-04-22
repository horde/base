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
use Horde\Horde\Service\SqlOAuthConsentRepository;
use Horde\OAuth\Server\Entity\Consent;
use Horde\OAuth\Server\Repository\ConsentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlOAuthConsentRepository::class)]
final class SqlOAuthConsentRepositoryTest extends TestCase
{
    private Sqlite $db;
    private SqlOAuthConsentRepository $repository;

    protected function setUp(): void
    {
        $this->db = new Sqlite(['dbname' => ':memory:']);
        $this->db->execute('
            CREATE TABLE horde_oauth_consents (
                identity_id VARCHAR(255) NOT NULL,
                client_id VARCHAR(255) NOT NULL,
                scope TEXT NOT NULL,
                granted_at INTEGER NOT NULL,
                UNIQUE (identity_id, client_id)
            )
        ');
        $this->repository = new SqlOAuthConsentRepository($this->db);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(ConsentRepository::class, $this->repository);
    }

    public function testPersistAndFindConsent(): void
    {
        $consent = new Consent(
            identityId: 'id-1',
            clientId: 'app-1',
            scope: 'openid email profile',
            grantedAt: new DateTimeImmutable('@1000000'),
        );
        $this->repository->persist($consent);

        $loaded = $this->repository->findConsent('id-1', 'app-1');

        self::assertNotNull($loaded);
        self::assertSame('id-1', $loaded->identityId);
        self::assertSame('app-1', $loaded->clientId);
        self::assertSame('openid email profile', $loaded->scope);
        self::assertSame(1000000, $loaded->grantedAt->getTimestamp());
    }

    public function testFindConsentReturnsNullForMissing(): void
    {
        self::assertNull($this->repository->findConsent('id-1', 'app-1'));
    }

    public function testPersistUpsertsScope(): void
    {
        $this->repository->persist(new Consent(
            identityId: 'id-1',
            clientId: 'app-1',
            scope: 'openid',
            grantedAt: new DateTimeImmutable('@1000000'),
        ));

        $this->repository->persist(new Consent(
            identityId: 'id-1',
            clientId: 'app-1',
            scope: 'openid email profile',
            grantedAt: new DateTimeImmutable('@1000100'),
        ));

        $loaded = $this->repository->findConsent('id-1', 'app-1');

        self::assertSame('openid email profile', $loaded->scope);
        self::assertSame(1000100, $loaded->grantedAt->getTimestamp());
    }

    public function testRevokeDeletesConsent(): void
    {
        $this->repository->persist(new Consent(
            identityId: 'id-1',
            clientId: 'app-1',
            scope: 'openid',
            grantedAt: new DateTimeImmutable('@1000000'),
        ));

        $this->repository->revoke('id-1', 'app-1');

        self::assertNull($this->repository->findConsent('id-1', 'app-1'));
    }

    public function testDifferentClientsSeparated(): void
    {
        $this->repository->persist(new Consent(
            identityId: 'id-1',
            clientId: 'app-1',
            scope: 'openid',
            grantedAt: new DateTimeImmutable('@1000000'),
        ));
        $this->repository->persist(new Consent(
            identityId: 'id-1',
            clientId: 'app-2',
            scope: 'email',
            grantedAt: new DateTimeImmutable('@1000000'),
        ));

        $c1 = $this->repository->findConsent('id-1', 'app-1');
        $c2 = $this->repository->findConsent('id-1', 'app-2');

        self::assertSame('openid', $c1->scope);
        self::assertSame('email', $c2->scope);
    }

    public function testCoversScope(): void
    {
        $this->repository->persist(new Consent(
            identityId: 'id-1',
            clientId: 'app-1',
            scope: 'openid email profile',
            grantedAt: new DateTimeImmutable('@1000000'),
        ));

        $consent = $this->repository->findConsent('id-1', 'app-1');

        self::assertTrue($consent->coversScope('openid email'));
        self::assertFalse($consent->coversScope('openid admin'));
    }
}
