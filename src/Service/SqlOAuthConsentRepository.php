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
use Horde\OAuth\Server\Entity\Consent;
use Horde\OAuth\Server\Repository\ConsentRepository;

class SqlOAuthConsentRepository implements ConsentRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly string $table = 'horde_oauth_consents',
    ) {}

    public function findConsent(string $identityId, string $clientId): ?Consent
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE identity_id = ? AND client_id = ?',
            [$identityId, $clientId]
        );

        if (empty($row)) {
            return null;
        }

        return new Consent(
            identityId: $row['identity_id'],
            clientId: $row['client_id'],
            scope: $row['scope'],
            grantedAt: (new DateTimeImmutable())->setTimestamp((int) $row['granted_at']),
        );
    }

    public function persist(Consent $consent): void
    {
        $affected = $this->db->update(
            'UPDATE ' . $this->table
            . ' SET scope = ?, granted_at = ?'
            . ' WHERE identity_id = ? AND client_id = ?',
            [
                $consent->scope,
                $consent->grantedAt->getTimestamp(),
                $consent->identityId,
                $consent->clientId,
            ]
        );

        if ($affected === 0) {
            $exists = $this->db->selectValue(
                'SELECT COUNT(*) FROM ' . $this->table
                . ' WHERE identity_id = ? AND client_id = ?',
                [$consent->identityId, $consent->clientId]
            );

            if ((int) $exists === 0) {
                $this->db->insert(
                    'INSERT INTO ' . $this->table
                    . ' (identity_id, client_id, scope, granted_at)'
                    . ' VALUES (?, ?, ?, ?)',
                    [
                        $consent->identityId,
                        $consent->clientId,
                        $consent->scope,
                        $consent->grantedAt->getTimestamp(),
                    ]
                );
            }
        }
    }

    public function revoke(string $identityId, string $clientId): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE identity_id = ? AND client_id = ?',
            [$identityId, $clientId]
        );
    }
}
