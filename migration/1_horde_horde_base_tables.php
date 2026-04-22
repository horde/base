<?php

class HordeHordeBaseTables extends Horde_Db_Migration_Base
{
    public function up()
    {
        // OAuth client token storage (Horde as OAuth client)
        if (!in_array('horde_oauth_tokens', $this->tables())) {
            $t = $this->createTable('horde_oauth_tokens', ['autoincrementKey' => 'token_id']);
            $t->column('user_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('provider_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('token_data', 'text', ['null' => false]);
            $t->column('created_at', 'integer', ['null' => false]);
            $t->column('updated_at', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('horde_oauth_tokens', ['user_uid', 'provider_id'], ['unique' => true]);
        }

        // OAuth provider configuration (Horde as OAuth client)
        if (!in_array('horde_oauth_providers', $this->tables())) {
            $t = $this->createTable('horde_oauth_providers', ['autoincrementKey' => 'id']);
            $t->column('provider_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('type', 'string', ['limit' => 20, 'null' => false]);
            $t->column('name', 'string', ['limit' => 255, 'null' => false]);
            $t->column('issuer', 'string', ['limit' => 1024]);
            $t->column('enabled', 'integer', ['null' => false, 'default' => 1]);
            $t->column('client_id', 'string', ['limit' => 255]);
            $t->column('client_secret', 'text');
            $t->column('authorization_endpoint', 'string', ['limit' => 1024]);
            $t->column('token_endpoint', 'string', ['limit' => 1024]);
            $t->column('userinfo_endpoint', 'string', ['limit' => 1024]);
            $t->column('jwks_uri', 'string', ['limit' => 1024]);
            $t->column('revocation_endpoint', 'string', ['limit' => 1024]);
            $t->column('introspection_endpoint', 'string', ['limit' => 1024]);
            $t->column('scopes_supported', 'text');
            $t->column('response_types_supported', 'text');
            $t->column('grant_types_supported', 'text');
            $t->column('token_endpoint_auth_methods_supported', 'text');
            $t->column('id_token_signing_alg_values_supported', 'text');
            $t->column('default_scopes', 'string', ['limit' => 1024]);
            $t->column('redirect_uri', 'string', ['limit' => 1024]);
            $t->column('app_identifier', 'string', ['limit' => 255]);
            $t->column('private_key', 'text');
            $t->column('installation_id', 'string', ['limit' => 255]);
            $t->column('display_label', 'string', ['limit' => 255]);
            $t->column('display_icon', 'string', ['limit' => 100]);
            $t->column('display_color', 'string', ['limit' => 20]);
            $t->column('created_at', 'integer', ['null' => false]);
            $t->column('updated_at', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('horde_oauth_providers', ['provider_id'], ['unique' => true]);
            $this->addIndex('horde_oauth_providers', ['enabled']);
        }

        // Identity management
        if (!in_array('horde_identities', $this->tables())) {
            $t = $this->createTable('horde_identities', ['autoincrementKey' => false, 'primaryKey' => 'identity_id']);
            $t->column('identity_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('role', 'string', ['limit' => 20, 'null' => false]);
            $t->column('status', 'string', ['limit' => 20, 'null' => false]);
            $t->column('display_name', 'string', ['limit' => 255]);
            $t->column('primary_email', 'string', ['limit' => 255]);
            $t->column('emails', 'text');
            $t->column('superseded_by', 'string', ['limit' => 255]);
            $t->column('created_at', 'integer', ['null' => false]);
            $t->column('updated_at', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('horde_identities', ['primary_email']);
            $this->addIndex('horde_identities', ['status']);
        }

        // Identity event log
        if (!in_array('horde_identity_events', $this->tables())) {
            $t = $this->createTable('horde_identity_events', ['autoincrementKey' => 'event_id']);
            $t->column('identity_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('event_type', 'string', ['limit' => 100, 'null' => false]);
            $t->column('actor_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('occurred_at', 'integer', ['null' => false]);
            $t->column('payload', 'text', ['null' => false]);
            $t->end();

            $this->addIndex('horde_identity_events', ['identity_id']);
            $this->addIndex('horde_identity_events', ['identity_id', 'occurred_at']);
        }

        // Identity ↔ auth provider links
        if (!in_array('horde_identity_auth_links', $this->tables())) {
            $t = $this->createTable('horde_identity_auth_links', ['autoincrementKey' => 'link_id']);
            $t->column('identity_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('provider', 'string', ['limit' => 255, 'null' => false]);
            $t->column('external_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('external_email', 'string', ['limit' => 255]);
            $t->column('external_display_name', 'string', ['limit' => 255]);
            $t->column('linked_at', 'integer', ['null' => false]);
            $t->column('last_used_at', 'integer');
            $t->column('metadata', 'text');
            $t->end();

            $this->addIndex('horde_identity_auth_links', ['identity_id']);
            $this->addIndex('horde_identity_auth_links', ['provider', 'external_id'], ['unique' => true]);
        }

        // OAuth server: registered clients
        if (!in_array('horde_oauth_clients', $this->tables())) {
            $t = $this->createTable('horde_oauth_clients', ['autoincrementKey' => false, 'primaryKey' => 'client_id']);
            $t->column('client_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('client_secret_hash', 'string', ['limit' => 255]);
            $t->column('client_name', 'string', ['limit' => 255, 'null' => false]);
            $t->column('redirect_uris', 'text', ['null' => false]);
            $t->column('grant_types', 'text', ['null' => false]);
            $t->column('scope', 'text', ['null' => false]);
            $t->column('client_type', 'string', ['limit' => 20, 'null' => false]);
            $t->column('token_endpoint_auth_method', 'string', ['limit' => 50, 'null' => false, 'default' => 'client_secret_basic']);
            $t->column('created_at', 'integer', ['null' => false]);
            $t->column('updated_at', 'integer', ['null' => false]);
            $t->end();
        }

        // OAuth server: access tokens
        if (!in_array('horde_oauth_access_tokens', $this->tables())) {
            $t = $this->createTable('horde_oauth_access_tokens', ['autoincrementKey' => false, 'primaryKey' => 'token_id']);
            $t->column('token_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('client_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('identity_id', 'string', ['limit' => 255]);
            $t->column('scope', 'text', ['null' => false]);
            $t->column('expires_at', 'integer', ['null' => false]);
            $t->column('revoked', 'integer', ['null' => false, 'default' => 0]);
            $t->end();

            $this->addIndex('horde_oauth_access_tokens', ['identity_id']);
        }

        // OAuth server: refresh tokens
        if (!in_array('horde_oauth_refresh_tokens', $this->tables())) {
            $t = $this->createTable('horde_oauth_refresh_tokens', ['autoincrementKey' => false, 'primaryKey' => 'token_id']);
            $t->column('token_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('access_token_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('client_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('identity_id', 'string', ['limit' => 255]);
            $t->column('scope', 'text', ['null' => false]);
            $t->column('expires_at', 'integer', ['null' => false]);
            $t->column('revoked', 'integer', ['null' => false, 'default' => 0]);
            $t->end();

            $this->addIndex('horde_oauth_refresh_tokens', ['access_token_id']);
        }

        // OAuth server: authorization codes
        if (!in_array('horde_oauth_authorization_codes', $this->tables())) {
            $t = $this->createTable('horde_oauth_authorization_codes', ['autoincrementKey' => false, 'primaryKey' => 'code']);
            $t->column('code', 'string', ['limit' => 255, 'null' => false]);
            $t->column('client_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('identity_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('redirect_uri', 'string', ['limit' => 1024, 'null' => false]);
            $t->column('scope', 'text', ['null' => false]);
            $t->column('code_challenge', 'string', ['limit' => 255]);
            $t->column('code_challenge_method', 'string', ['limit' => 10]);
            $t->column('nonce', 'string', ['limit' => 255]);
            $t->column('expires_at', 'integer', ['null' => false]);
            $t->column('used', 'integer', ['null' => false, 'default' => 0]);
            $t->end();
        }

        // OAuth server: user consent records
        if (!in_array('horde_oauth_consents', $this->tables())) {
            $t = $this->createTable('horde_oauth_consents', ['autoincrementKey' => false, 'primaryKey' => false]);
            $t->column('identity_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('client_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('scope', 'text', ['null' => false]);
            $t->column('granted_at', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('horde_oauth_consents', ['identity_id', 'client_id'], ['unique' => true]);
        }

        // OAuth server: scope definitions
        if (!in_array('horde_oauth_scopes', $this->tables())) {
            $t = $this->createTable('horde_oauth_scopes', ['autoincrementKey' => false, 'primaryKey' => 'identifier']);
            $t->column('identifier', 'string', ['limit' => 255, 'null' => false]);
            $t->end();
        }
    }

    public function down()
    {
        $tables = $this->tables();
        foreach ([
            'horde_oauth_scopes',
            'horde_oauth_consents',
            'horde_oauth_authorization_codes',
            'horde_oauth_refresh_tokens',
            'horde_oauth_access_tokens',
            'horde_oauth_clients',
            'horde_identity_auth_links',
            'horde_identity_events',
            'horde_identities',
            'horde_oauth_providers',
            'horde_oauth_tokens',
        ] as $table) {
            if (in_array($table, $tables)) {
                $this->dropTable($table);
            }
        }
    }
}
