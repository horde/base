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
use Horde\Identity\Exception\IdentityNotFoundException;
use Horde\Identity\Identity;
use Horde\Identity\IdentityRepository;
use Horde\Identity\IdentityRole;
use Horde\Identity\IdentityStatus;

class SqlIdentityRepository implements IdentityRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly string $table = 'horde_identities',
    ) {}

    public function get(string $id): Identity
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE identity_id = ?',
            [$id]
        );

        if (empty($row)) {
            throw new IdentityNotFoundException(
                sprintf('Identity "%s" not found', $id)
            );
        }

        return $this->hydrate($row);
    }

    public function save(Identity $identity): void
    {
        $emails = json_encode($identity->emails, JSON_THROW_ON_ERROR);
        $createdAt = $identity->createdAt->getTimestamp();
        $updatedAt = $identity->updatedAt->getTimestamp();

        $affected = $this->db->update(
            'UPDATE ' . $this->table
            . ' SET role = ?, status = ?, display_name = ?, primary_email = ?,'
            . ' emails = ?, superseded_by = ?, created_at = ?, updated_at = ?'
            . ' WHERE identity_id = ?',
            [
                $identity->role->value,
                $identity->status->value,
                $identity->displayName,
                $identity->primaryEmail,
                $emails,
                $identity->supersededBy,
                $createdAt,
                $updatedAt,
                $identity->id,
            ]
        );

        if ($affected === 0 && !$this->exists($identity->id)) {
            $this->db->insert(
                'INSERT INTO ' . $this->table
                . ' (identity_id, role, status, display_name, primary_email,'
                . ' emails, superseded_by, created_at, updated_at)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $identity->id,
                    $identity->role->value,
                    $identity->status->value,
                    $identity->displayName,
                    $identity->primaryEmail,
                    $emails,
                    $identity->supersededBy,
                    $createdAt,
                    $updatedAt,
                ]
            );
        }
    }

    public function delete(string $id): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE identity_id = ?',
            [$id]
        );
    }

    public function exists(string $id): bool
    {
        $count = $this->db->selectValue(
            'SELECT COUNT(*) FROM ' . $this->table . ' WHERE identity_id = ?',
            [$id]
        );

        return (int) $count > 0;
    }

    public function findByEmail(string $email): ?Identity
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE primary_email = ?',
            [$email]
        );

        if (empty($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    /** @return list<Identity> */
    public function findSupersededBy(string $identityId): array
    {
        $rows = $this->db->selectAll(
            'SELECT * FROM ' . $this->table . ' WHERE superseded_by = ?',
            [$identityId]
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    private function hydrate(array $row): Identity
    {
        $emails = $row['emails'] !== null
            ? json_decode($row['emails'], true, 512, JSON_THROW_ON_ERROR)
            : [];

        return new Identity(
            id: $row['identity_id'],
            role: IdentityRole::from($row['role']),
            status: IdentityStatus::from($row['status']),
            displayName: $row['display_name'],
            primaryEmail: $row['primary_email'],
            emails: $emails,
            supersededBy: $row['superseded_by'],
            createdAt: (new DateTimeImmutable())->setTimestamp((int) $row['created_at']),
            updatedAt: (new DateTimeImmutable())->setTimestamp((int) $row['updated_at']),
        );
    }
}
