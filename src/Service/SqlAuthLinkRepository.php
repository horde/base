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

class SqlAuthLinkRepository implements AuthLinkRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly string $table = 'horde_identity_auth_links',
    ) {}

    public function resolve(string $provider, string $externalId): ?AuthLink
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE provider = ? AND external_id = ?',
            [$provider, $externalId]
        );

        if (empty($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByIdentity(string $identityId): array
    {
        $rows = $this->db->selectAll(
            'SELECT * FROM ' . $this->table . ' WHERE identity_id = ? ORDER BY linked_at ASC',
            [$identityId]
        );

        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function save(AuthLink $link): AuthLink
    {
        if ($link->linkId === 0) {
            return $this->insert($link);
        }

        return $this->update($link);
    }

    public function delete(int $linkId): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE link_id = ?',
            [$linkId]
        );
    }

    public function deleteByProviderAndExternalId(string $provider, string $externalId): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE provider = ? AND external_id = ?',
            [$provider, $externalId]
        );
    }

    public function updateLastUsed(int $linkId, DateTimeImmutable $at): void
    {
        $this->db->update(
            'UPDATE ' . $this->table . ' SET last_used_at = ? WHERE link_id = ?',
            [$at->getTimestamp(), $linkId]
        );
    }

    private function insert(AuthLink $link): AuthLink
    {
        $metadata = $link->metadata !== null
            ? json_encode($link->metadata, JSON_THROW_ON_ERROR)
            : null;

        $id = $this->db->insert(
            'INSERT INTO ' . $this->table
            . ' (identity_id, provider, external_id, external_email,'
            . ' external_display_name, linked_at, last_used_at, metadata)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $link->identityId,
                $link->provider,
                $link->externalId,
                $link->externalEmail,
                $link->externalDisplayName,
                $link->linkedAt->getTimestamp(),
                $link->lastUsedAt?->getTimestamp(),
                $metadata,
            ]
        );

        return new AuthLink(
            linkId: (int) $id,
            identityId: $link->identityId,
            provider: $link->provider,
            externalId: $link->externalId,
            externalEmail: $link->externalEmail,
            externalDisplayName: $link->externalDisplayName,
            linkedAt: $link->linkedAt,
            lastUsedAt: $link->lastUsedAt,
            metadata: $link->metadata,
        );
    }

    private function update(AuthLink $link): AuthLink
    {
        $metadata = $link->metadata !== null
            ? json_encode($link->metadata, JSON_THROW_ON_ERROR)
            : null;

        $this->db->update(
            'UPDATE ' . $this->table
            . ' SET identity_id = ?, provider = ?, external_id = ?, external_email = ?,'
            . ' external_display_name = ?, linked_at = ?, last_used_at = ?, metadata = ?'
            . ' WHERE link_id = ?',
            [
                $link->identityId,
                $link->provider,
                $link->externalId,
                $link->externalEmail,
                $link->externalDisplayName,
                $link->linkedAt->getTimestamp(),
                $link->lastUsedAt?->getTimestamp(),
                $metadata,
                $link->linkId,
            ]
        );

        return $link;
    }

    private function hydrate(array $row): AuthLink
    {
        $metadata = $row['metadata'] !== null
            ? json_decode($row['metadata'], true, 512, JSON_THROW_ON_ERROR)
            : null;

        return new AuthLink(
            linkId: (int) $row['link_id'],
            identityId: $row['identity_id'],
            provider: $row['provider'],
            externalId: $row['external_id'],
            externalEmail: $row['external_email'] ?? null,
            externalDisplayName: $row['external_display_name'] ?? null,
            linkedAt: (new DateTimeImmutable())->setTimestamp((int) $row['linked_at']),
            lastUsedAt: $row['last_used_at'] !== null
                ? (new DateTimeImmutable())->setTimestamp((int) $row['last_used_at'])
                : null,
            metadata: $metadata,
        );
    }
}
