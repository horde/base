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
use Horde\OAuth\Server\Entity\Client;
use Horde\OAuth\Server\Repository\ClientRepository;

class SqlOAuthClientRepository implements ClientRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly string $table = 'horde_oauth_clients',
    ) {}

    public function findById(string $clientId): ?Client
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE client_id = ?',
            [$clientId]
        );

        if (empty($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function validateClient(string $clientId, ?string $clientSecret, string $grantType): bool
    {
        $client = $this->findById($clientId);
        if ($client === null) {
            return false;
        }

        if (!$client->supportsGrantType($grantType)) {
            return false;
        }

        if ($client->isConfidential()) {
            if ($clientSecret === null) {
                return false;
            }
            return $client->verifySecret($clientSecret);
        }

        return true;
    }

    private function hydrate(array $row): Client
    {
        return new Client(
            clientId: $row['client_id'],
            clientSecretHash: $row['client_secret_hash'] ?? null,
            clientName: $row['client_name'],
            redirectUris: json_decode($row['redirect_uris'], true, 512, JSON_THROW_ON_ERROR),
            grantTypes: json_decode($row['grant_types'], true, 512, JSON_THROW_ON_ERROR),
            scope: $row['scope'],
            clientType: $row['client_type'],
            tokenEndpointAuthMethod: $row['token_endpoint_auth_method'],
            createdAt: (new DateTimeImmutable())->setTimestamp((int) $row['created_at']),
            updatedAt: (new DateTimeImmutable())->setTimestamp((int) $row['updated_at']),
        );
    }
}
