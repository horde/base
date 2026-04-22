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

use Horde\Db\Adapter;
use Horde\OAuth\Server\Entity\Client;
use Horde\OAuth\Server\Entity\Scope;
use Horde\OAuth\Server\Repository\ScopeRepository;

class SqlOAuthScopeRepository implements ScopeRepository
{
    public function __construct(
        private readonly Adapter $db,
        private readonly string $table = 'horde_oauth_scopes',
    ) {}

    public function findByIdentifier(string $identifier): ?Scope
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE identifier = ?',
            [$identifier]
        );

        if (empty($row)) {
            return null;
        }

        return new Scope($row['identifier']);
    }

    public function finalizeScopes(
        array $scopes,
        string $grantType,
        Client $client,
        ?string $identityId = null,
    ): array {
        if ($scopes === []) {
            return $client->getDefaultScopes();
        }

        return array_values(array_filter(
            $scopes,
            fn(Scope $scope) => $this->findByIdentifier($scope->identifier) !== null,
        ));
    }
}
