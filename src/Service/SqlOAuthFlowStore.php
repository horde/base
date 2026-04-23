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
use Horde\Db\Query\DeleteBuilder;
use Horde\Db\Query\InsertBuilder;
use Horde\Db\Query\SelectBuilder;
use Horde\OAuth\Client\OAuthFlowData;
use Horde\OAuth\Client\OAuthFlowStore;

final class SqlOAuthFlowStore implements OAuthFlowStore
{
    public function __construct(
        private readonly Adapter $db,
        private readonly string $table = 'horde_oauth_flows',
    ) {}

    public function save(string $state, OAuthFlowData $data): void
    {
        $query = (new InsertBuilder($this->db))
            ->into($this->table)
            ->values([
                'state_hash' => hash('sha256', $state),
                'provider_id' => $data->providerId,
                'pkce_verifier' => $data->pkceVerifier,
                'flow_type' => $data->flowType,
                'redirect_url' => $data->redirectUrl !== '' ? $data->redirectUrl : null,
                'requesting_app' => $data->requestingApp !== '' ? $data->requestingApp : null,
                'created_at' => $data->createdAt,
            ])
            ->build();

        $this->db->insert($query->sql, $query->params);
    }

    public function consume(string $state): ?OAuthFlowData
    {
        $stateHash = hash('sha256', $state);

        $select = (new SelectBuilder($this->db))
            ->from($this->table)
            ->where('state_hash', '=', $stateHash)
            ->build();

        $row = $this->db->selectOne($select->sql, $select->params);

        if (empty($row)) {
            return null;
        }

        $delete = (new DeleteBuilder($this->db))
            ->from($this->table)
            ->where('state_hash', '=', $stateHash)
            ->build();

        $this->db->delete($delete->sql, $delete->params);

        return new OAuthFlowData(
            state: $state,
            providerId: (string) $row['provider_id'],
            pkceVerifier: (string) $row['pkce_verifier'],
            flowType: (string) $row['flow_type'],
            createdAt: (int) $row['created_at'],
            redirectUrl: (string) ($row['redirect_url'] ?? ''),
            requestingApp: (string) ($row['requesting_app'] ?? ''),
        );
    }
}
