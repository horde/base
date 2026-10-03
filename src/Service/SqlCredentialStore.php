<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Service\CredentialStore;
use Horde\Core\Service\PasswordCredential;
use Horde\Core\Service\ServicePurpose;
use Horde\Db\Adapter;
use Horde\Secret\EncryptedData;
use Horde\Secret\SecretManager;
use RuntimeException;

/**
 *  SQL-backed CredentialStore with libsodium encryption.
 *
 *  Should be used via the child SqlCredentialStoreFactory hooked into Core's primary Horde\Core\Service\Factory\CredentialStoreFactory.
 */
class SqlCredentialStore implements CredentialStore
{
    public function __construct(
        private readonly Adapter $db,
        private readonly SecretManager $secret,
        private readonly string $table = 'horde_password_credentials',
    ) {}

    public function find(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ?PasswordCredential {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ? AND purpose_id = ?',
            [$userId, $providerId, $purpose->identifier()]
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(string $userId): array
    {
        $rows = $this->db->selectAll(
            'SELECT * FROM ' . $this->table . ' WHERE user_uid = ?',
            [$userId]
        );

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findAllForProvider(
        string $userId,
        string $providerId
    ): array {
        $rows = $this->db->selectAll(
            'SELECT * FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ?',
            [$userId, $providerId]
        );

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function store(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
        array|string $credential
    ): PasswordCredential {
        $credentialId = $this->generateId();
        $json = json_encode($credential, JSON_THROW_ON_ERROR);
        $encrypted = $this->secret->encrypt($json);
        $credentialData = $encrypted->toBase64();
        $now = time();

        $this->db->insert(
            'INSERT INTO ' . $this->table . ' (credential_id, user_uid, provider_id, purpose_id, credential_data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$credentialId, $userId, $providerId, $purpose->identifier(), $credentialData, $now, $now]
        );

        return new HordePasswordCredential(
            $credentialId,
            $userId,
            $providerId,
            $purpose,
            $credential,
            $now,
            $now
        );
    }

    public function update(
        string $credentialId,
        array|string $credential
    ): PasswordCredential {
        $json = json_encode($credential, JSON_THROW_ON_ERROR);
        $encrypted = $this->secret->encrypt($json);
        $credentialData = $encrypted->toBase64();
        $now = time();

        $this->db->update(
            'UPDATE ' . $this->table . ' SET credential_data = ?, updated_at = ? WHERE credential_id = ?',
            [$credentialData, $now, $credentialId]
        );

        // Fetch to return full object
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE credential_id = ?',
            [$credentialId]
        );

        if (!$row) {
            throw new RuntimeException("Credential not found after update: {$credentialId}");
        }

        return $this->hydrate($row);
    }

    public function delete(string $credentialId): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE credential_id = ?',
            [$credentialId]
        );
    }

    public function deleteAll(string $userId, string $providerId): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ?',
            [$userId, $providerId]
        );
    }

    private function hydrate(array $row): PasswordCredential
    {
        $encrypted = EncryptedData::fromBase64($row['credential_data']);
        $json = $this->secret->decrypt($encrypted);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // purpose_id stores the bare identifier string (the lookup key used
        // by find() and the unique index), not the full serialize() array.
        // Reconstruct with the default grant strategy, matching how
        // ServicePurpose::equals() compares on identifier alone.
        $purpose = ServicePurpose::of($row['purpose_id']);

        return new HordePasswordCredential(
            $row['credential_id'],
            $row['user_uid'],
            $row['provider_id'],
            $purpose,
            $data,
            (int) $row['created_at'],
            (int) $row['updated_at']
        );
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
