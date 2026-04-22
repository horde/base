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
use Horde\OAuth\Server\Entity\AccessToken;
use Horde\OAuth\Server\Repository\AccessTokenRepository;

class SqlOAuthAccessTokenRepository implements AccessTokenRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly string $table = 'horde_oauth_access_tokens',
    ) {}

    public function persist(AccessToken $token): void
    {
        $this->db->insert(
            'INSERT INTO ' . $this->table
            . ' (token_id, client_id, identity_id, scope, expires_at, revoked)'
            . ' VALUES (?, ?, ?, ?, ?, ?)',
            [
                $token->tokenId,
                $token->clientId,
                $token->identityId,
                $token->scope,
                $token->expiresAt->getTimestamp(),
                $token->revoked ? 1 : 0,
            ]
        );
    }

    public function findById(string $tokenId): ?AccessToken
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE token_id = ?',
            [$tokenId]
        );

        if (empty($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function revoke(string $tokenId): void
    {
        $this->db->update(
            'UPDATE ' . $this->table . ' SET revoked = 1 WHERE token_id = ?',
            [$tokenId]
        );
    }

    public function isRevoked(string $tokenId): bool
    {
        $revoked = $this->db->selectValue(
            'SELECT revoked FROM ' . $this->table . ' WHERE token_id = ?',
            [$tokenId]
        );

        return (int) $revoked === 1;
    }

    private function hydrate(array $row): AccessToken
    {
        return new AccessToken(
            tokenId: $row['token_id'],
            clientId: $row['client_id'],
            identityId: $row['identity_id'] ?? null,
            scope: $row['scope'],
            expiresAt: (new DateTimeImmutable())->setTimestamp((int) $row['expires_at']),
            revoked: (int) $row['revoked'] === 1,
        );
    }
}
