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
use Horde\OAuth\Server\Entity\AuthorizationCode;
use Horde\OAuth\Server\Repository\AuthorizationCodeRepository;

class SqlOAuthAuthorizationCodeRepository implements AuthorizationCodeRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly string $table = 'horde_oauth_authorization_codes',
    ) {}

    public function persist(AuthorizationCode $code): void
    {
        $this->db->insert(
            'INSERT INTO ' . $this->table
            . ' (code, client_id, identity_id, redirect_uri, scope,'
            . ' code_challenge, code_challenge_method, nonce, expires_at, used)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $code->code,
                $code->clientId,
                $code->identityId,
                $code->redirectUri,
                $code->scope,
                $code->codeChallenge,
                $code->codeChallengeMethod,
                $code->nonce,
                $code->expiresAt->getTimestamp(),
                $code->used ? 1 : 0,
            ]
        );
    }

    public function findByCode(string $code): ?AuthorizationCode
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE code = ?',
            [$code]
        );

        if (empty($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function markUsed(string $code): void
    {
        $this->db->update(
            'UPDATE ' . $this->table . ' SET used = 1 WHERE code = ?',
            [$code]
        );
    }

    public function isUsed(string $code): bool
    {
        $used = $this->db->selectValue(
            'SELECT used FROM ' . $this->table . ' WHERE code = ?',
            [$code]
        );

        return (int) $used === 1;
    }

    private function hydrate(array $row): AuthorizationCode
    {
        return new AuthorizationCode(
            code: $row['code'],
            clientId: $row['client_id'],
            identityId: $row['identity_id'],
            redirectUri: $row['redirect_uri'],
            scope: $row['scope'],
            codeChallenge: $row['code_challenge'] ?? null,
            codeChallengeMethod: $row['code_challenge_method'] ?? null,
            nonce: $row['nonce'] ?? null,
            expiresAt: (new DateTimeImmutable())->setTimestamp((int) $row['expires_at']),
            used: (int) $row['used'] === 1,
        );
    }
}
