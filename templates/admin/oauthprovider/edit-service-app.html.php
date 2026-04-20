<h1 class="header"><?php echo sprintf(_("Edit Service App: %s"), $this->h($this->provider['name'])) ?></h1>

<form method="post" action="<?php echo $this->h($this->baseUrl) ?>/<?php echo $this->h($this->provider['provider_id']) ?>" class="headerbox">
  <div class="horde-form">
    <div class="horde-form-row">
      <label><?php echo _("Provider ID") ?></label>
      <strong><?php echo $this->h($this->provider['provider_id']) ?></strong>
    </div>

    <div class="horde-form-row">
      <label><?php echo _("Type") ?></label>
      <strong><?php echo _("Service App (JWT assertion)") ?></strong>
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

    <div class="horde-form-row">
      <label for="app_identifier"><?php echo _("App Identifier") ?></label>
      <input type="text" id="app_identifier" name="app_identifier" value="<?php echo $this->h($this->provider['app_identifier'] ?? '') ?>" />
      <span class="horde-form-description"><?php echo _("The application ID (e.g. GitHub App ID).") ?></span>
    </div>

    <div class="horde-form-row">
      <label for="installation_id"><?php echo _("Installation ID") ?></label>
      <input type="text" id="installation_id" name="installation_id" value="<?php echo $this->h($this->provider['installation_id'] ?? '') ?>" />
      <span class="horde-form-description"><?php echo _("The installation-scoped identifier.") ?></span>
    </div>

    <div class="horde-form-row">
      <label for="private_key"><?php echo _("Private Key (PEM)") ?></label>
      <textarea id="private_key" name="private_key" rows="6" cols="60" placeholder="<?php echo !empty($this->provider['private_key']) ? '********' : '' ?>"></textarea>
      <?php if (!empty($this->provider['private_key'])): ?>
      <label class="horde-form-checkbox">
        <input type="checkbox" name="_replace_private_key" value="1" />
        <?php echo _("Replace existing key") ?>
      </label>
      <?php endif ?>
    </div>

    <div class="horde-form-row">
      <label for="token_endpoint"><?php echo _("Token Endpoint") ?></label>
      <input type="url" id="token_endpoint" name="token_endpoint" value="<?php echo $this->h($this->provider['token_endpoint'] ?? '') ?>" />
      <span class="horde-form-description"><?php echo _("Endpoint to exchange JWT assertion for an access token.") ?></span>
    </div>

    <div class="horde-form-row">
      <label for="default_scopes"><?php echo _("Default Scopes") ?></label>
      <input type="text" id="default_scopes" name="default_scopes" value="<?php echo $this->h($this->provider['default_scopes'] ?? '') ?>" />
    </div>

    <div class="horde-form-actions">
      <input type="submit" class="horde-default" value="<?php echo _("Save") ?>" />
      <a href="<?php echo $this->h($this->baseUrl) ?>/"><?php echo _("Cancel") ?></a>
    </div>
  </div>
</form>
