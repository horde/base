<?php
/**
 * Enhance OAUTH related schema to support purpose-specific authorization and token grants.
 */
class HordeTokenGrants extends Horde_Db_Migration_Base
{
    public function up()
    {
        if (!in_array('horde_token_grants', $this->tables())) {
            $t = $this->createTable('horde_token_grants', ['autoincrementKey' => false, 'primaryKey' => 'grant_id']);
            $t->column('grant_id', 'string', ['limit' => 36, 'null' => false]);
            $t->column('user_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('provider_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('token_data', 'text', ['null' => false]);
            $t->column('granted_scopes', 'text', ['null' => false]);
            $t->column('is_shared', 'integer', ['limit' => 1, 'null' => false, 'default' => 0]);
            $t->column('created_at', 'integer', ['null' => false]);
            $t->column('updated_at', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('horde_token_grants', ['user_uid', 'provider_id']);
            $this->addIndex('horde_token_grants', ['user_uid', 'provider_id', 'is_shared']);
        }
        if (!in_array('horde_service_authorizations', $this->tables())) {
            $t = $this->createTable('horde_service_authorizations', ['autoincrementKey' => false, 'primaryKey' => ['user_uid', 'provider_id', 'purpose_id']]);
            $t->column('user_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('provider_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('purpose_id', 'string', ['limit' => 100, 'null' => false]);
            $t->column('purpose_strategy', 'string', ['limit' => 20, 'null' => false]);
            $t->column('grant_id', 'string', ['limit' => 36, 'null' => false]);
            $t->column('created_at', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('horde_service_authorizations', ['grant_id']);
        }
        if (in_array('horde_oauth_flows', $this->tables())) {
            $this->changeColumn('horde_oauth_flows', 'flow_type', 'string', ['limit' => 255, 'null' => false]);
        }
        if (in_array('horde_oauth_providers', $this->tables())) {
            $this->addColumn('horde_oauth_providers', 'purposes', 'text');
        }

    }

    public function down()
    {
        if (in_array('horde_oauth_flows', $this->tables())) {
            $this->changeColumn('horde_oauth_flows', 'flow_type', 'string', ['limit' => 50, 'null' => false]);
        }
        if (in_array('horde_oauth_providers', $this->tables())) {
            $this->removeColumn('horde_oauth_providers', 'purposes');
        }
        $this->dropTable('horde_token_grants');
        $this->dropTable('horde_service_authorizations');
    }
}
