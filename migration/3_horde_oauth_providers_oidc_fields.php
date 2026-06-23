<?php
// migration/3_horde_oauth_providers_oidc_fields.php

class HordeOauthProvidersOidcFields extends Horde_Db_Migration_Base
{
    public function up()
    {
        $cols = array_column(
            $this->columns('horde_oauth_providers'),
            null,
            'name'
        );

        if (!isset($cols['logout_type'])) {
            $this->addColumn('horde_oauth_providers', 'logout_type',
                'string', ['limit' => 20, 'default' => 'local']);
        }
        if (!isset($cols['end_session_endpoint'])) {
            $this->addColumn('horde_oauth_providers', 'end_session_endpoint',
                'string', ['limit' => 1024]);
        }
        if (!isset($cols['post_logout_redirect_uri'])) {
            $this->addColumn('horde_oauth_providers', 'post_logout_redirect_uri',
                'string', ['limit' => 1024]);
        }
        if (!isset($cols['backchannel_username_claim'])) {
            $this->addColumn('horde_oauth_providers', 'backchannel_username_claim',
                'string', ['limit' => 64, 'default' => 'sub']);
        }
        if (!isset($cols['xoauth2_use_email'])) {
            $this->addColumn('horde_oauth_providers', 'xoauth2_use_email',
                'integer', ['default' => 0]);
        }
        if (!isset($cols['xoauth2_domain'])) {
            $this->addColumn('horde_oauth_providers', 'xoauth2_domain',
                'string', ['limit' => 255]);
        }
    }

    public function down()
    {
        foreach ([
            'logout_type', 'end_session_endpoint', 'post_logout_redirect_uri',
            'backchannel_username_claim', 'xoauth2_use_email', 'xoauth2_domain',
        ] as $col) {
            $this->removeColumn('horde_oauth_providers', $col);
        }
    }
}
