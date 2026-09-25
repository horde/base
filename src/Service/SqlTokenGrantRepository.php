<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Service\TokenGrant;
use Horde\Core\Service\TokenGrantRepository;
use Horde\Db\Adapter;
use Horde\OAuth\Client\ScopeSet;
use Horde\OAuth\Client\TokenSet;
use Horde\Secret\EncryptedData;
use Horde\Secret\SecretManager;

/** SQL-backed TokenGrant repository with encryption. */
class SqlTokenGrantRepository implements TokenGrantRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly SecretManager $secret,
        private readonly string $table = 'horde_token_grants',
    ) {}

    public function findShared(string $userId, string $providerId): ?TokenGrant
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ? AND is_shared = 1',
            [$userId, $providerId]
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findById(string $grantId): ?TokenGrant
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE grant_id = ?',
            [$grantId]
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(string $userId, string $providerId): array
    {
        $rows = $this->db->selectAll(
            'SELECT * FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ?',
            [$userId, $providerId]
        );

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function save(TokenGrant $grant): void
    {
        $tokenSet = $grant->tokenSet();
        $json = json_encode($tokenSet->toArray(), JSON_THROW_ON_ERROR);
        $encrypted = $this->secret->encrypt($json);
        $tokenData = $encrypted->toBase64();
        $grantedScopes = implode(' ', $grant->grantedScopes()->toArray());
        $isShared = $grant instanceof MutableTokenGrant ? $grant->isShared() : 0;
        $now = time();

        $this->db->insert(
            'INSERT INTO ' . $this->table . ' (grant_id, user_uid, provider_id, token_data, granted_scopes, is_shared, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$grant->grantId(), $grant->userId(), $grant->providerId(), $tokenData, $grantedScopes, $isShared ? 1 : 0, $now, $now]
        );
    }

    public function update(TokenGrant $grant): void
    {
        $tokenSet = $grant->tokenSet();
        $json = json_encode($tokenSet->toArray(), JSON_THROW_ON_ERROR);
        $encrypted = $this->secret->encrypt($json);
        $tokenData = $encrypted->toBase64();
        $grantedScopes = implode(' ', $grant->grantedScopes()->toArray());
        $now = time();

        $this->db->update(
            'UPDATE ' . $this->table . ' SET token_data = ?, granted_scopes = ?, updated_at = ? WHERE grant_id = ?',
            [$tokenData, $grantedScopes, $now, $grant->grantId()]
        );
    }

    public function delete(TokenGrant $grant): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE grant_id = ?',
            [$grant->grantId()]
        );
    }

    private function hydrate(array $row): TokenGrant
    {
        $encrypted = EncryptedData::fromBase64($row['token_data']);
        $json = $this->secret->decrypt($encrypted);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $tokenSet = TokenSet::fromArray($data);

        $grantedScopes = !empty($row['granted_scopes'])
            ? ScopeSet::fromSpaceSeparated($row['granted_scopes'])
            : new ScopeSet();

        return new MutableTokenGrant(
            grantId: $row['grant_id'],
            userId: $row['user_uid'],
            providerId: $row['provider_id'],
            tokenSet: $tokenSet,
            grantedScopes: $grantedScopes,
            isShared: (bool) $row['is_shared'],
            repository: $this,
        );
    }
}
