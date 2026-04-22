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

use Horde\Core\Service\Exception\OAuthProviderConfigNotFoundException;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Db\Adapter;
use Horde\Secret\EncryptedData;
use Horde\Secret\SecretManager;

/**
 * SQL-backed OAuth provider configuration repository with encryption at rest.
 *
 * Sensitive fields (client_secret, private_key) are encrypted via SecretManager.
 * JSON array columns are transparently encoded/decoded.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class SqlOAuthProviderConfigRepository implements OAuthProviderConfigRepository
{
    private const ENCRYPTED_FIELDS = ['client_secret', 'private_key'];

    private const JSON_FIELDS = [
        'scopes_supported',
        'response_types_supported',
        'grant_types_supported',
        'token_endpoint_auth_methods_supported',
        'id_token_signing_alg_values_supported',
    ];

    public function __construct(
        private readonly Adapter $db,
        private readonly ?SecretManager $secret,
        private readonly string $table = 'horde_oauth_providers',
    ) {}

    public function get(string $providerId): array
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE provider_id = ?',
            [$providerId]
        );

        if (empty($row)) {
            throw new OAuthProviderConfigNotFoundException(
                "No provider config for '{$providerId}'"
            );
        }

        return $this->decryptRow($row);
    }

    public function listAll(): array
    {
        $rows = $this->db->selectAll(
            'SELECT * FROM ' . $this->table . ' ORDER BY name'
        );

        return array_map([$this, 'decryptRow'], $rows);
    }

    public function listEnabled(): array
    {
        $rows = $this->db->selectAll(
            'SELECT * FROM ' . $this->table . ' WHERE enabled = 1 ORDER BY name'
        );

        return array_map([$this, 'decryptRow'], $rows);
    }

    public function save(string $providerId, array $data): void
    {
        $now = time();
        $prepared = $this->prepareRow($data);

        $affected = $this->db->selectValue(
            'SELECT COUNT(*) FROM ' . $this->table . ' WHERE provider_id = ?',
            [$providerId]
        );

        if ((int) $affected > 0) {
            $setClauses = [];
            $params = [];
            foreach ($prepared as $column => $value) {
                if ($column === 'id' || $column === 'provider_id') {
                    continue;
                }
                $setClauses[] = $column . ' = ?';
                $params[] = $value;
            }
            $setClauses[] = 'updated_at = ?';
            $params[] = $now;
            $params[] = $providerId;

            $this->db->update(
                'UPDATE ' . $this->table . ' SET ' . implode(', ', $setClauses) . ' WHERE provider_id = ?',
                $params
            );
        } else {
            $prepared['provider_id'] = $providerId;
            $prepared['created_at'] = $now;
            $prepared['updated_at'] = $now;
            unset($prepared['id']);

            $columns = array_keys($prepared);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));

            $this->db->insert(
                'INSERT INTO ' . $this->table . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
                array_values($prepared)
            );
        }
    }

    public function delete(string $providerId): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE provider_id = ?',
            [$providerId]
        );
    }

    public function exists(string $providerId): bool
    {
        $count = $this->db->selectValue(
            'SELECT COUNT(*) FROM ' . $this->table . ' WHERE provider_id = ?',
            [$providerId]
        );

        return (int) $count > 0;
    }

    public function hasEncryption(): bool
    {
        return $this->secret !== null;
    }

    private function decryptRow(array $row): array
    {
        if ($this->secret !== null) {
            foreach (self::ENCRYPTED_FIELDS as $field) {
                if (!empty($row[$field])) {
                    try {
                        $encrypted = EncryptedData::fromBase64($row[$field]);
                        $row[$field] = $this->secret->decrypt($encrypted);
                    } catch (\InvalidArgumentException) {
                        // Value is not encrypted — pass through as-is
                    }
                }
            }
        }

        foreach (self::JSON_FIELDS as $field) {
            if (!empty($row[$field])) {
                $row[$field] = json_decode($row[$field], true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return $row;
    }

    private function prepareRow(array $data): array
    {
        if ($this->secret !== null) {
            foreach (self::ENCRYPTED_FIELDS as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    $encrypted = $this->secret->encrypt($data[$field]);
                    $data[$field] = $encrypted->toBase64();
                }
            }
        }

        foreach (self::JSON_FIELDS as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field], JSON_THROW_ON_ERROR);
            }
        }

        return $data;
    }
}
