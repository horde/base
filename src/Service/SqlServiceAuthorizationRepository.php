<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Service\GrantStrategy;
use Horde\Core\Service\ServiceAuthorization;
use Horde\Core\Service\ServiceAuthorizationRepository;
use Horde\Core\Service\ServicePurpose;
use Horde\Core\Service\TokenGrantRepository;
use Horde\Db\Adapter;
use Horde\OAuth\Client\ScopeSet;

/** SQL-backed ServiceAuthorization repository. */
class SqlServiceAuthorizationRepository implements ServiceAuthorizationRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly TokenGrantRepository $grantRepo,
        private readonly string $table = 'horde_service_authorizations',
    ) {}

    public function find(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
    ): ?ServiceAuthorization {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ? AND purpose_id = ?',
            [$userId, $providerId, $purpose->identifier()]
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

    public function findAllForUser(string $userId): array
    {
        $rows = $this->db->selectAll(
            'SELECT * FROM ' . $this->table . ' WHERE user_uid = ?',
            [$userId]
        );

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function save(ServiceAuthorization $authorization): void
    {
        $now = time();

        $this->db->insert(
            'INSERT INTO ' . $this->table . ' (user_uid, provider_id, purpose_id, purpose_strategy, grant_id, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $authorization->userId(),
                $authorization->providerId(),
                $authorization->purpose()->identifier(),
                $authorization->purpose()->grantStrategy()->value,
                $authorization->grant()->grantId(),
                $now
            ]
        );
    }

    public function delete(ServiceAuthorization $authorization): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ? AND purpose_id = ?',
            [
                $authorization->userId(),
                $authorization->providerId(),
                $authorization->purpose()->identifier()
            ]
        );
    }

    public function deleteAll(string $userId, string $providerId): void
    {
        $this->db->delete(
            'DELETE FROM ' . $this->table . ' WHERE user_uid = ? AND provider_id = ?',
            [$userId, $providerId]
        );
    }

    private function hydrate(array $row): ServiceAuthorization
    {
        $purpose = ServicePurpose::of(
            $row['purpose_id'],
            GrantStrategy::from($row['purpose_strategy'])
        );

        $grant = $this->grantRepo->findById($row['grant_id']);
        if ($grant === null) {
            throw new \RuntimeException(
                "TokenGrant '{$row['grant_id']}' not found for ServiceAuthorization"
            );
        }

        // Required scopes are determined by provider config, we store empty for now
        $requiredScopes = new ScopeSet();

        return new ConcreteServiceAuthorization(
            userId: $row['user_uid'],
            providerId: $row['provider_id'],
            purpose: $purpose,
            grant: $grant,
            requiredScopes: $requiredScopes,
        );
    }
}
