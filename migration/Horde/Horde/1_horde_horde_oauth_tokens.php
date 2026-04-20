<?php

class HordeHordeOauthTables extends Horde_Db_Migration_Base
{
    public function up()
    {
        if (!in_array('horde_oauth_tokens', $this->tables())) {
            $t = $this->createTable('horde_oauth_tokens', ['autoincrementKey' => ['token_id']]);
            $t->column('token_id', 'integer', ['null' => false]);
            $t->column('user_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('provider_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('token_data', 'text', ['null' => false]);
            $t->column('created_at', 'integer', ['null' => false]);
            $t->column('updated_at', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('horde_oauth_tokens', ['user_uid', 'provider_id'], ['unique' => true]);
        }

        if (!in_array('horde_oauth_providers', $this->tables())) {
            $t = $this->createTable('horde_oauth_providers', ['autoincrementKey' => ['id']]);
            $t->column('id', 'integer', ['null' => false]);
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
            $t->column('created_at', 'integer', ['null' => false]);
            $t->column('updated_at', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('horde_oauth_providers', ['provider_id'], ['unique' => true]);
            $this->addIndex('horde_oauth_providers', ['enabled']);
        }
    }

    public function down()
    {
        $this->dropTable('horde_oauth_providers');
        $this->dropTable('horde_oauth_tokens');
    }
}
