<?php

class HordeOAuthFlows extends Horde_Db_Migration_Base
{
    public function up()
    {
        if (!in_array('horde_oauth_flows', $this->tables())) {
            $t = $this->createTable('horde_oauth_flows', ['autoincrementKey' => 'id']);
            $t->column('state_hash', 'string', ['limit' => 64, 'null' => false]);
            $t->column('provider_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('pkce_verifier', 'string', ['limit' => 255, 'null' => false]);
            $t->column('flow_type', 'string', ['limit' => 50, 'null' => false]);
            $t->column('redirect_url', 'string', ['limit' => 1024]);
            $t->column('requesting_app', 'string', ['limit' => 255]);
            $t->column('created_at', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('horde_oauth_flows', ['state_hash'], ['unique' => true]);
            $this->addIndex('horde_oauth_flows', ['created_at']);
        }
    }

    public function down()
    {
        $this->dropTable('horde_oauth_flows');
    }
}
