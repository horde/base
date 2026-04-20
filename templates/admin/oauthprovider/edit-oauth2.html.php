<h1 class="header"><?php echo sprintf(_("Edit Provider: %s"), $this->h($this->provider['name'])) ?></h1>

<form method="post" action="<?php echo $this->h($this->baseUrl) ?>/<?php echo $this->h($this->provider['provider_id']) ?>" class="headerbox">
  <div class="horde-form">
    <div class="horde-form-row">
      <label><?php echo _("Provider ID") ?></label>
      <strong><?php echo $this->h($this->provider['provider_id']) ?></strong>
    </div>

    <div class="horde-form-row">
      <label><?php echo _("Type") ?></label>
      <strong><?php echo $this->h($this->provider['type']) ?></strong>
    </div>

    <div class="horde-form-row">
      <label for="name"><?php echo _("Display Name") ?></label>
      <input type="text" id="name" name="name" required value="<?php echo $this->h($this->provider['name']) ?>" />
    </div>

    <div class="horde-form-row">
      <label for="enabled"><?php echo _("Enabled") ?></label>
      <select id="enabled" name="enabled">
        <option value="1"<?php if (!empty($this->provider['enabled'])): ?> selected<?php endif ?>><?php echo _("Yes") ?></option>
        <option value="0"<?php if (empty($this->provider['enabled'])): ?> selected<?php endif ?>><?php echo _("No") ?></option>
      </select>
    </div>

    <div class="horde-form-row">
      <label for="issuer"><?php echo _("Issuer URL") ?></label>
      <input type="url" id="issuer" name="issuer" value="<?php echo $this->h($this->provider['issuer'] ?? '') ?>" />
    </div>

    <?php if ($this->provider['type'] === 'oidc'): ?>
    <div class="horde-form-row">
      <label>&nbsp;</label>
      <button type="submit" name="_action" value="discover"><?php echo _("Auto-discover endpoints from issuer") ?></button>
    </div>
    <?php endif ?>

    <div class="horde-form-row">
      <label for="client_id"><?php echo _("Client ID") ?></label>
      <input type="text" id="client_id" name="client_id" value="<?php echo $this->h($this->provider['client_id'] ?? '') ?>" />
    </div>

    <div class="horde-form-row">
      <label for="client_secret"><?php echo _("Client Secret") ?></label>
      <input type="password" id="client_secret" name="client_secret" value="" placeholder="<?php echo !empty($this->provider['client_secret']) ? '********' : '' ?>" />
      <?php if (!empty($this->provider['client_secret'])): ?>
      <label class="horde-form-checkbox">
        <input type="checkbox" name="_replace_client_secret" value="1" />
        <?php echo _("Replace existing secret") ?>
      </label>
      <?php endif ?>
    </div>

    <div class="horde-form-row">
      <label for="authorization_endpoint"><?php echo _("Authorization Endpoint") ?></label>
      <input type="url" id="authorization_endpoint" name="authorization_endpoint" value="<?php echo $this->h($this->provider['authorization_endpoint'] ?? '') ?>" />
    </div>

    <div class="horde-form-row">
      <label for="token_endpoint"><?php echo _("Token Endpoint") ?></label>
      <input type="url" id="token_endpoint" name="token_endpoint" value="<?php echo $this->h($this->provider['token_endpoint'] ?? '') ?>" />
    </div>

    <div class="horde-form-row">
      <label for="userinfo_endpoint"><?php echo _("Userinfo Endpoint") ?></label>
      <input type="url" id="userinfo_endpoint" name="userinfo_endpoint" value="<?php echo $this->h($this->provider['userinfo_endpoint'] ?? '') ?>" />
    </div>

    <div class="horde-form-row">
      <label for="jwks_uri"><?php echo _("JWKS URI") ?></label>
      <input type="url" id="jwks_uri" name="jwks_uri" value="<?php echo $this->h($this->provider['jwks_uri'] ?? '') ?>" />
    </div>

    <div class="horde-form-row">
      <label for="revocation_endpoint"><?php echo _("Revocation Endpoint") ?></label>
      <input type="url" id="revocation_endpoint" name="revocation_endpoint" value="<?php echo $this->h($this->provider['revocation_endpoint'] ?? '') ?>" />
    </div>

    <div class="horde-form-row">
      <label for="introspection_endpoint"><?php echo _("Introspection Endpoint") ?></label>
      <input type="url" id="introspection_endpoint" name="introspection_endpoint" value="<?php echo $this->h($this->provider['introspection_endpoint'] ?? '') ?>" />
    </div>

    <div class="horde-form-row">
      <label for="default_scopes"><?php echo _("Default Scopes") ?></label>
      <input type="text" id="default_scopes" name="default_scopes" value="<?php echo $this->h($this->provider['default_scopes'] ?? '') ?>" placeholder="openid email profile" />
    </div>

    <div class="horde-form-row">
      <label for="redirect_uri"><?php echo _("Redirect URI") ?></label>
      <input type="url" id="redirect_uri" name="redirect_uri" value="<?php echo $this->h($this->provider['redirect_uri'] ?? '') ?>" />
    </div>

    <div class="horde-form-actions">
      <input type="submit" class="horde-default" value="<?php echo _("Save") ?>" />
      <a href="<?php echo $this->h($this->baseUrl) ?>/"><?php echo _("Cancel") ?></a>
    </div>
  </div>
</form>
