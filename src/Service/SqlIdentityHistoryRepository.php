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

namespace Horde\Horde\Service;

use DateTimeImmutable;
use Horde\Db\Adapter;
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
use Horde\Identity\IdentityHistory;
use Horde\Identity\IdentityHistoryRepository;
use Horde\Identity\IdentityRole;
use Horde\Identity\IdentityStatus;
use InvalidArgumentException;

class SqlIdentityHistoryRepository implements IdentityHistoryRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly string $table = 'horde_identity_events',
    ) {}

    public function append(IdentityEvent $event): void
    {
        $this->db->insert(
            'INSERT INTO ' . $this->table
            . ' (identity_id, event_type, actor_id, occurred_at, payload)'
            . ' VALUES (?, ?, ?, ?, ?)',
            [
                $event->identityId,
                $this->eventTypeName($event),
                $event->actorId,
                $event->occurredAt->getTimestamp(),
                $this->serializePayload($event),
            ]
        );
    }

    public function getHistory(string $identityId): IdentityHistory
    {
        $rows = $this->db->selectAll(
            'SELECT * FROM ' . $this->table
            . ' WHERE identity_id = ? ORDER BY occurred_at ASC, event_id ASC',
            [$identityId]
        );

        $events = array_map(
            fn(array $row): IdentityEvent => $this->deserializeEvent($row),
            $rows
        );

        return new IdentityHistory($events);
    }

    public function getHistorySince(string $identityId, DateTimeImmutable $since): IdentityHistory
    {
        $rows = $this->db->selectAll(
            'SELECT * FROM ' . $this->table
            . ' WHERE identity_id = ? AND occurred_at >= ?'
            . ' ORDER BY occurred_at ASC, event_id ASC',
            [$identityId, $since->getTimestamp()]
        );

        $events = array_map(
            fn(array $row): IdentityEvent => $this->deserializeEvent($row),
            $rows
        );

        return new IdentityHistory($events);
    }

    private function eventTypeName(IdentityEvent $event): string
    {
        return match (true) {
            $event instanceof IdentityCreated => 'IdentityCreated',
            $event instanceof DisplayNameChanged => 'DisplayNameChanged',
            $event instanceof PrimaryEmailChanged => 'PrimaryEmailChanged',
            $event instanceof EmailAdded => 'EmailAdded',
            $event instanceof EmailRemoved => 'EmailRemoved',
            $event instanceof RoleChanged => 'RoleChanged',
            $event instanceof StatusChanged => 'StatusChanged',
            $event instanceof IdentityScrubbed => 'IdentityScrubbed',
            $event instanceof IdentitiesMerged => 'IdentitiesMerged',
            $event instanceof IdentityRetired => 'IdentityRetired',
            default => throw new InvalidArgumentException('Unknown event type: ' . $event::class),
        };
    }

    private function serializePayload(IdentityEvent $event): string
    {
        $data = match (true) {
            $event instanceof IdentityCreated => [
                'role' => $event->role->value,
                'displayName' => $event->displayName,
                'primaryEmail' => $event->primaryEmail,
                'emails' => $event->emails,
            ],
            $event instanceof DisplayNameChanged => [
                'oldValue' => $event->oldValue,
                'newValue' => $event->newValue,
            ],
            $event instanceof PrimaryEmailChanged => [
                'oldValue' => $event->oldValue,
                'newValue' => $event->newValue,
            ],
            $event instanceof EmailAdded => [
                'email' => $event->email,
            ],
            $event instanceof EmailRemoved => [
                'email' => $event->email,
            ],
            $event instanceof RoleChanged => [
                'oldRole' => $event->oldRole->value,
                'newRole' => $event->newRole->value,
            ],
            $event instanceof StatusChanged => [
                'oldStatus' => $event->oldStatus->value,
                'newStatus' => $event->newStatus->value,
            ],
            $event instanceof IdentityScrubbed => [],
            $event instanceof IdentitiesMerged => [
                'retiredIdentityIds' => $event->retiredIdentityIds,
                'absorbedEmails' => $event->absorbedEmails,
                'absorbedDisplayName' => $event->absorbedDisplayName,
            ],
            $event instanceof IdentityRetired => [
                'supersededBy' => $event->supersededBy,
            ],
            default => throw new InvalidArgumentException('Unknown event type: ' . $event::class),
        };

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function deserializeEvent(array $row): IdentityEvent
    {
        $identityId = $row['identity_id'];
        $actorId = $row['actor_id'];
        $occurredAt = (new DateTimeImmutable())->setTimestamp((int) $row['occurred_at']);
        $payload = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);

        return match ($row['event_type']) {
            'IdentityCreated' => new IdentityCreated(
                identityId: $identityId,
                role: IdentityRole::from($payload['role']),
                displayName: $payload['displayName'],
                primaryEmail: $payload['primaryEmail'],
                emails: $payload['emails'],
                actorId: $actorId,
                occurredAt: $occurredAt,
            ),
            'DisplayNameChanged' => new DisplayNameChanged(
                identityId: $identityId,
                oldValue: $payload['oldValue'],
                newValue: $payload['newValue'],
                actorId: $actorId,
                occurredAt: $occurredAt,
            ),
            'PrimaryEmailChanged' => new PrimaryEmailChanged(
                identityId: $identityId,
                oldValue: $payload['oldValue'],
                newValue: $payload['newValue'],
                actorId: $actorId,
                occurredAt: $occurredAt,
            ),
            'EmailAdded' => new EmailAdded(
                identityId: $identityId,
                email: $payload['email'],
                actorId: $actorId,
                occurredAt: $occurredAt,
            ),
            'EmailRemoved' => new EmailRemoved(
                identityId: $identityId,
                email: $payload['email'],
                actorId: $actorId,
                occurredAt: $occurredAt,
            ),
            'RoleChanged' => new RoleChanged(
                identityId: $identityId,
                oldRole: IdentityRole::from($payload['oldRole']),
                newRole: IdentityRole::from($payload['newRole']),
                actorId: $actorId,
                occurredAt: $occurredAt,
            ),
            'StatusChanged' => new StatusChanged(
                identityId: $identityId,
                oldStatus: IdentityStatus::from($payload['oldStatus']),
                newStatus: IdentityStatus::from($payload['newStatus']),
                actorId: $actorId,
                occurredAt: $occurredAt,
            ),
            'IdentityScrubbed' => new IdentityScrubbed(
                identityId: $identityId,
                actorId: $actorId,
                occurredAt: $occurredAt,
            ),
            'IdentitiesMerged' => new IdentitiesMerged(
                identityId: $identityId,
                retiredIdentityIds: $payload['retiredIdentityIds'],
                absorbedEmails: $payload['absorbedEmails'],
                absorbedDisplayName: $payload['absorbedDisplayName'],
                actorId: $actorId,
                occurredAt: $occurredAt,
            ),
            'IdentityRetired' => new IdentityRetired(
                identityId: $identityId,
                supersededBy: $payload['supersededBy'],
                actorId: $actorId,
                occurredAt: $occurredAt,
            ),
            default => throw new InvalidArgumentException(
                sprintf('Unknown event type "%s"', $row['event_type'])
            ),
        };
    }
}
