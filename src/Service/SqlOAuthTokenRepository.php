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

use Horde\Core\Service\Exception\OAuthTokenNotFoundException;
use Horde\Core\Service\OAuthTokenRepository;
use Horde\Db\Adapter;
use Horde\OAuth\Client\TokenSet;
use Horde\Secret\EncryptedData;
use Horde\Secret\SecretManager;

/**
 * SQL-backed OAuth token repository with encryption at rest.
 *
 * Token data is encrypted via SecretManager using the installation-wide
 * secret_key before storage. Supports any Horde_Db_Adapter backend.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class SqlOAuthTokenRepository implements OAuthTokenRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly SecretManager $secret,
        private readonly string $table = 'horde_oauth_tokens',
    ) {}

    public function load(string $userId, string $providerId): TokenSet
    {
        $row = $this->db->selectOne(
            'SELECT token_data FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ?',
            [$userId, $providerId]
        );

        if (empty($row)) {
            throw new OAuthTokenNotFoundException(
                "No OAuth tokens for user '{$userId}' / provider '{$providerId}'"
            );
        }

        $encrypted = EncryptedData::fromBase64($row['token_data']);
        $json = $this->secret->decrypt($encrypted);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return TokenSet::fromArray($data);
    }

    public function save(string $userId, string $providerId, TokenSet $tokens): void
    {
        $json = json_encode($tokens->toArray(), JSON_THROW_ON_ERROR);
        $encrypted = $this->secret->encrypt($json);
        $tokenData = $encrypted->toBase64();
        $now = time();

        $affected = $this->db->update(
            'UPDATE ' . $this->table . ' SET token_data = ?, updated_at = ? WHERE user_uid = ? AND provider_id = ?',
            [$tokenData, $now, $userId, $providerId]
        );

        if ($affected === 0) {
            $this->db->insert(
                'INSERT INTO ' . $this->table . ' (user_uid, provider_id, token_data, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                [$userId, $providerId, $tokenData, $now, $now]
            );
        }
    }

    public function exists(string $userId, string $providerId): bool
    {
        $count = $this->db->selectValue(
            'SELECT COUNT(*) FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ?',
            [$userId, $providerId]
        );

        return (int) $count > 0;
    }

    public function delete(string $userId, string $providerId): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ?',
            [$userId, $providerId]
        );
    }
}
