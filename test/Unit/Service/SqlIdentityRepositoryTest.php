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
use Horde\Horde\Service\SqlIdentityRepository;
use Horde\Identity\Exception\IdentityNotFoundException;
use Horde\Identity\Identity;
use Horde\Identity\IdentityRepository;
use Horde\Identity\IdentityRole;
use Horde\Identity\IdentityStatus;
use Horde\Db\Adapter\Pdo\Sqlite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlIdentityRepository::class)]
final class SqlIdentityRepositoryTest extends TestCase
{
    private Sqlite $db;
    private SqlIdentityRepository $repository;

    protected function setUp(): void
    {
        $this->db = new Sqlite(['dbname' => ':memory:']);
        $this->db->execute('
            CREATE TABLE horde_identities (
                identity_id VARCHAR(255) NOT NULL PRIMARY KEY,
                role VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL,
                display_name VARCHAR(255),
                primary_email VARCHAR(255),
                emails TEXT,
                superseded_by VARCHAR(255),
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            )
        ');
        $this->repository = new SqlIdentityRepository($this->db);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(IdentityRepository::class, $this->repository);
    }

    public function testSaveAndGet(): void
    {
        $identity = $this->makeIdentity();
        $this->repository->save($identity);

        $loaded = $this->repository->get($identity->id);

        self::assertSame($identity->id, $loaded->id);
        self::assertSame(IdentityRole::Principal, $loaded->role);
        self::assertSame(IdentityStatus::Active, $loaded->status);
        self::assertSame('Alice', $loaded->displayName);
        self::assertSame('alice@example.com', $loaded->primaryEmail);
        self::assertSame(['alice@example.com', 'alice@work.com'], $loaded->emails);
        self::assertNull($loaded->supersededBy);
        self::assertSame($identity->createdAt->getTimestamp(), $loaded->createdAt->getTimestamp());
        self::assertSame($identity->updatedAt->getTimestamp(), $loaded->updatedAt->getTimestamp());
    }

    public function testUpsert(): void
    {
        $identity = $this->makeIdentity();
        $this->repository->save($identity);

        $updated = new Identity(
            id: $identity->id,
            role: IdentityRole::Collaborator,
            status: IdentityStatus::Suspended,
            displayName: 'Alice Updated',
            primaryEmail: 'newalice@example.com',
            emails: ['newalice@example.com'],
            supersededBy: null,
            createdAt: $identity->createdAt,
            updatedAt: new DateTimeImmutable('@' . (time() + 100)),
        );
        $this->repository->save($updated);

        $loaded = $this->repository->get($identity->id);

        self::assertSame('Alice Updated', $loaded->displayName);
        self::assertSame(IdentityRole::Collaborator, $loaded->role);
        self::assertSame(IdentityStatus::Suspended, $loaded->status);
        self::assertSame(['newalice@example.com'], $loaded->emails);
    }

    public function testDelete(): void
    {
        $identity = $this->makeIdentity();
        $this->repository->save($identity);
        self::assertTrue($this->repository->exists($identity->id));

        $this->repository->delete($identity->id);

        self::assertFalse($this->repository->exists($identity->id));
    }

    public function testExistsReturnsFalseForMissing(): void
    {
        self::assertFalse($this->repository->exists('nonexistent'));
    }

    public function testGetThrowsOnMissing(): void
    {
        $this->expectException(IdentityNotFoundException::class);
        $this->repository->get('nonexistent');
    }

    public function testFindByEmailReturnsIdentity(): void
    {
        $identity = $this->makeIdentity();
        $this->repository->save($identity);

        $found = $this->repository->findByEmail('alice@example.com');

        self::assertNotNull($found);
        self::assertSame($identity->id, $found->id);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->repository->findByEmail('nobody@example.com'));
    }

    public function testNullableFields(): void
    {
        $identity = new Identity(
            id: 'id-null-fields',
            role: IdentityRole::Collaborator,
            status: IdentityStatus::Active,
            displayName: null,
            primaryEmail: null,
            emails: [],
            supersededBy: null,
            createdAt: new DateTimeImmutable('@1000000'),
            updatedAt: new DateTimeImmutable('@1000000'),
        );
        $this->repository->save($identity);

        $loaded = $this->repository->get('id-null-fields');

        self::assertNull($loaded->displayName);
        self::assertNull($loaded->primaryEmail);
        self::assertSame([], $loaded->emails);
        self::assertNull($loaded->supersededBy);
    }

    public function testSupersededByRoundtrip(): void
    {
        $identity = new Identity(
            id: 'id-retired',
            role: IdentityRole::Principal,
            status: IdentityStatus::Retired,
            displayName: 'Old User',
            primaryEmail: 'old@example.com',
            emails: ['old@example.com'],
            supersededBy: 'id-surviving',
            createdAt: new DateTimeImmutable('@1000000'),
            updatedAt: new DateTimeImmutable('@1000100'),
        );
        $this->repository->save($identity);

        $loaded = $this->repository->get('id-retired');

        self::assertSame('id-surviving', $loaded->supersededBy);
        self::assertSame(IdentityStatus::Retired, $loaded->status);
    }

    private function makeIdentity(): Identity
    {
        return new Identity(
            id: 'id-' . bin2hex(random_bytes(8)),
            role: IdentityRole::Principal,
            status: IdentityStatus::Active,
            displayName: 'Alice',
            primaryEmail: 'alice@example.com',
            emails: ['alice@example.com', 'alice@work.com'],
            supersededBy: null,
            createdAt: new DateTimeImmutable('@1000000'),
            updatedAt: new DateTimeImmutable('@1000050'),
        );
    }
}
