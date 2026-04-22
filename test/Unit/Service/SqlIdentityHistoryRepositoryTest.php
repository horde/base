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
use Horde\Horde\Service\SqlIdentityHistoryRepository;
use Horde\Identity\Event\DisplayNameChanged;
use Horde\Identity\Event\EmailAdded;
use Horde\Identity\Event\EmailRemoved;
use Horde\Identity\Event\IdentitiesMerged;
use Horde\Identity\Event\IdentityCreated;
use Horde\Identity\Event\IdentityEvent;
use Horde\Identity\Event\IdentityRetired;
use Horde\Identity\Event\IdentityScrubbed;
use Horde\Identity\Event\PrimaryEmailChanged;
use Horde\Identity\Event\RoleChanged;
use Horde\Identity\Event\StatusChanged;
use Horde\Identity\IdentityHistoryRepository;
use Horde\Identity\IdentityRole;
use Horde\Identity\IdentityStatus;
use Horde\Db\Adapter\Pdo\Sqlite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlIdentityHistoryRepository::class)]
final class SqlIdentityHistoryRepositoryTest extends TestCase
{
    private Sqlite $db;
    private SqlIdentityHistoryRepository $repository;

    protected function setUp(): void
    {
        $this->db = new Sqlite(['dbname' => ':memory:']);
        $this->db->execute('
            CREATE TABLE horde_identity_events (
                event_id INTEGER PRIMARY KEY AUTOINCREMENT,
                identity_id VARCHAR(255) NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                actor_id VARCHAR(255) NOT NULL,
                occurred_at INTEGER NOT NULL,
                payload TEXT NOT NULL
            )
        ');
        $this->repository = new SqlIdentityHistoryRepository($this->db);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(IdentityHistoryRepository::class, $this->repository);
    }

    public function testAppendAndGetHistory(): void
    {
        $event = new IdentityCreated(
            identityId: 'id-1',
            role: IdentityRole::Principal,
            displayName: 'Alice',
            primaryEmail: 'alice@example.com',
            emails: ['alice@example.com'],
            actorId: 'system',
            occurredAt: new DateTimeImmutable('@1000000'),
        );

        $this->repository->append($event);
        $history = $this->repository->getHistory('id-1');

        self::assertCount(1, $history);
        $events = $history->toArray();
        self::assertInstanceOf(IdentityCreated::class, $events[0]);
        self::assertSame('id-1', $events[0]->identityId);
        self::assertSame('system', $events[0]->actorId);
        self::assertSame(1000000, $events[0]->occurredAt->getTimestamp());
        self::assertSame(IdentityRole::Principal, $events[0]->role);
        self::assertSame('Alice', $events[0]->displayName);
        self::assertSame('alice@example.com', $events[0]->primaryEmail);
        self::assertSame(['alice@example.com'], $events[0]->emails);
    }

    public function testGetHistorySince(): void
    {
        $this->repository->append(new IdentityCreated(
            identityId: 'id-1',
            role: IdentityRole::Principal,
            displayName: 'Alice',
            primaryEmail: 'alice@example.com',
            emails: [],
            actorId: 'system',
            occurredAt: new DateTimeImmutable('@1000000'),
        ));
        $this->repository->append(new DisplayNameChanged(
            identityId: 'id-1',
            oldValue: 'Alice',
            newValue: 'Alice B.',
            actorId: 'admin',
            occurredAt: new DateTimeImmutable('@1000100'),
        ));
        $this->repository->append(new EmailAdded(
            identityId: 'id-1',
            email: 'alice@work.com',
            actorId: 'admin',
            occurredAt: new DateTimeImmutable('@1000200'),
        ));

        $since = new DateTimeImmutable('@1000100');
        $history = $this->repository->getHistorySince('id-1', $since);

        self::assertCount(2, $history);
        $events = $history->toArray();
        self::assertInstanceOf(DisplayNameChanged::class, $events[0]);
        self::assertInstanceOf(EmailAdded::class, $events[1]);
    }

    public function testEmptyHistory(): void
    {
        $history = $this->repository->getHistory('nonexistent');
        self::assertCount(0, $history);
    }

    public function testEventOrdering(): void
    {
        $this->repository->append(new IdentityCreated(
            identityId: 'id-1',
            role: IdentityRole::Principal,
            displayName: 'A',
            primaryEmail: null,
            emails: [],
            actorId: 'sys',
            occurredAt: new DateTimeImmutable('@100'),
        ));
        $this->repository->append(new DisplayNameChanged(
            identityId: 'id-1',
            oldValue: 'A',
            newValue: 'B',
            actorId: 'sys',
            occurredAt: new DateTimeImmutable('@200'),
        ));
        $this->repository->append(new DisplayNameChanged(
            identityId: 'id-1',
            oldValue: 'B',
            newValue: 'C',
            actorId: 'sys',
            occurredAt: new DateTimeImmutable('@200'),
        ));

        $events = $this->repository->getHistory('id-1')->toArray();

        self::assertCount(3, $events);
        self::assertInstanceOf(IdentityCreated::class, $events[0]);
        self::assertSame('B', $events[1]->newValue);
        self::assertSame('C', $events[2]->newValue);
    }

    public function testIdentityCreatedRoundtrip(): void
    {
        $event = new IdentityCreated(
            identityId: 'id-rt',
            role: IdentityRole::Collaborator,
            displayName: null,
            primaryEmail: 'test@example.com',
            emails: ['test@example.com', 'alias@example.com'],
            actorId: 'actor-1',
            occurredAt: new DateTimeImmutable('@500000'),
        );
        $this->repository->append($event);

        $loaded = $this->repository->getHistory('id-rt')->toArray()[0];
        self::assertInstanceOf(IdentityCreated::class, $loaded);
        self::assertSame(IdentityRole::Collaborator, $loaded->role);
        self::assertNull($loaded->displayName);
        self::assertSame('test@example.com', $loaded->primaryEmail);
        self::assertSame(['test@example.com', 'alias@example.com'], $loaded->emails);
    }

    public function testDisplayNameChangedRoundtrip(): void
    {
        $event = new DisplayNameChanged(
            identityId: 'id-rt',
            oldValue: 'Old',
            newValue: 'New',
            actorId: 'actor-1',
            occurredAt: new DateTimeImmutable('@500000'),
        );
        $this->repository->append($event);

        $loaded = $this->repository->getHistory('id-rt')->toArray()[0];
        self::assertInstanceOf(DisplayNameChanged::class, $loaded);
        self::assertSame('Old', $loaded->oldValue);
        self::assertSame('New', $loaded->newValue);
    }

    public function testPrimaryEmailChangedRoundtrip(): void
    {
        $event = new PrimaryEmailChanged(
            identityId: 'id-rt',
            oldValue: 'old@example.com',
            newValue: null,
            actorId: 'actor-1',
            occurredAt: new DateTimeImmutable('@500000'),
        );
        $this->repository->append($event);

        $loaded = $this->repository->getHistory('id-rt')->toArray()[0];
        self::assertInstanceOf(PrimaryEmailChanged::class, $loaded);
        self::assertSame('old@example.com', $loaded->oldValue);
        self::assertNull($loaded->newValue);
    }

    public function testEmailAddedRoundtrip(): void
    {
        $event = new EmailAdded(
            identityId: 'id-rt',
            email: 'new@example.com',
            actorId: 'actor-1',
            occurredAt: new DateTimeImmutable('@500000'),
        );
        $this->repository->append($event);

        $loaded = $this->repository->getHistory('id-rt')->toArray()[0];
        self::assertInstanceOf(EmailAdded::class, $loaded);
        self::assertSame('new@example.com', $loaded->email);
    }

    public function testEmailRemovedRoundtrip(): void
    {
        $event = new EmailRemoved(
            identityId: 'id-rt',
            email: 'gone@example.com',
            actorId: 'actor-1',
            occurredAt: new DateTimeImmutable('@500000'),
        );
        $this->repository->append($event);

        $loaded = $this->repository->getHistory('id-rt')->toArray()[0];
        self::assertInstanceOf(EmailRemoved::class, $loaded);
        self::assertSame('gone@example.com', $loaded->email);
    }

    public function testRoleChangedRoundtrip(): void
    {
        $event = new RoleChanged(
            identityId: 'id-rt',
            oldRole: IdentityRole::Collaborator,
            newRole: IdentityRole::Principal,
            actorId: 'actor-1',
            occurredAt: new DateTimeImmutable('@500000'),
        );
        $this->repository->append($event);

        $loaded = $this->repository->getHistory('id-rt')->toArray()[0];
        self::assertInstanceOf(RoleChanged::class, $loaded);
        self::assertSame(IdentityRole::Collaborator, $loaded->oldRole);
        self::assertSame(IdentityRole::Principal, $loaded->newRole);
    }

    public function testStatusChangedRoundtrip(): void
    {
        $event = new StatusChanged(
            identityId: 'id-rt',
            oldStatus: IdentityStatus::Active,
            newStatus: IdentityStatus::Suspended,
            actorId: 'actor-1',
            occurredAt: new DateTimeImmutable('@500000'),
        );
        $this->repository->append($event);

        $loaded = $this->repository->getHistory('id-rt')->toArray()[0];
        self::assertInstanceOf(StatusChanged::class, $loaded);
        self::assertSame(IdentityStatus::Active, $loaded->oldStatus);
        self::assertSame(IdentityStatus::Suspended, $loaded->newStatus);
    }

    public function testIdentityScrubbedRoundtrip(): void
    {
        $event = new IdentityScrubbed(
            identityId: 'id-rt',
            actorId: 'gdpr-bot',
            occurredAt: new DateTimeImmutable('@500000'),
        );
        $this->repository->append($event);

        $loaded = $this->repository->getHistory('id-rt')->toArray()[0];
        self::assertInstanceOf(IdentityScrubbed::class, $loaded);
        self::assertSame('gdpr-bot', $loaded->actorId);
    }

    public function testIdentitiesMergedRoundtrip(): void
    {
        $event = new IdentitiesMerged(
            identityId: 'id-survivor',
            retiredIdentityIds: ['id-old-1', 'id-old-2'],
            absorbedEmails: ['old1@example.com', 'old2@example.com'],
            absorbedDisplayName: 'Old Display Name',
            actorId: 'admin',
            occurredAt: new DateTimeImmutable('@500000'),
        );
        $this->repository->append($event);

        $loaded = $this->repository->getHistory('id-survivor')->toArray()[0];
        self::assertInstanceOf(IdentitiesMerged::class, $loaded);
        self::assertSame(['id-old-1', 'id-old-2'], $loaded->retiredIdentityIds);
        self::assertSame(['old1@example.com', 'old2@example.com'], $loaded->absorbedEmails);
        self::assertSame('Old Display Name', $loaded->absorbedDisplayName);
    }

    public function testIdentityRetiredRoundtrip(): void
    {
        $event = new IdentityRetired(
            identityId: 'id-old',
            supersededBy: 'id-survivor',
            actorId: 'admin',
            occurredAt: new DateTimeImmutable('@500000'),
        );
        $this->repository->append($event);

        $loaded = $this->repository->getHistory('id-old')->toArray()[0];
        self::assertInstanceOf(IdentityRetired::class, $loaded);
        self::assertSame('id-survivor', $loaded->supersededBy);
    }

    public function testDifferentIdentitiesAreSeparated(): void
    {
        $this->repository->append(new IdentityCreated(
            identityId: 'id-1',
            role: IdentityRole::Principal,
            displayName: 'User 1',
            primaryEmail: null,
            emails: [],
            actorId: 'sys',
            occurredAt: new DateTimeImmutable('@100'),
        ));
        $this->repository->append(new IdentityCreated(
            identityId: 'id-2',
            role: IdentityRole::Collaborator,
            displayName: 'User 2',
            primaryEmail: null,
            emails: [],
            actorId: 'sys',
            occurredAt: new DateTimeImmutable('@200'),
        ));

        self::assertCount(1, $this->repository->getHistory('id-1'));
        self::assertCount(1, $this->repository->getHistory('id-2'));
    }
}
