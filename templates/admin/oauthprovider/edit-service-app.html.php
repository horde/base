<div class="settings-page">
  <h1 class="settings-page-title"><?php echo sprintf(_("Edit Service App: %s"), $this->h($this->provider['name'])) ?></h1>

  <?php if ($this->setupNotes !== ''): ?>
  <div class="settings-callout">
    <strong><?php echo _("Setup") ?></strong>
    <p><?php echo $this->h($this->setupNotes) ?></p>
  </div>
  <?php endif ?>

  <form method="post" action="<?php echo $this->h($this->baseUrl) ?>/<?php echo $this->h($this->provider['provider_id']) ?>">
    <div class="settings-form-card">

      <div class="settings-form-row">
        <span class="settings-form-label"><?php echo _("Provider ID") ?></span>
        <span class="settings-form-static"><?php echo $this->h($this->provider['provider_id']) ?></span>
      </div>

      <div class="settings-form-row">
        <span class="settings-form-label"><?php echo _("Type") ?></span>
        <span class="settings-form-static"><?php echo _("Service App (JWT assertion)") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="name"><?php echo _("Display Name") ?></label>
        <input type="text" id="name" name="name" class="settings-form-control" required value="<?php echo $this->h($this->provider['name']) ?>" />
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="enabled"><?php echo _("Enabled") ?></label>
        <select id="enabled" name="enabled" class="settings-form-control">
          <option value="1"<?php if (!empty($this->provider['enabled'])): ?> selected<?php endif ?>><?php echo _("Yes") ?></option>
          <option value="0"<?php if (empty($this->provider['enabled'])): ?> selected<?php endif ?>><?php echo _("No") ?></option>
        </select>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="issuer"><?php echo _("Issuer URL") ?></label>
        <input type="url" id="issuer" name="issuer" class="settings-form-control" value="<?php echo $this->h($this->provider['issuer'] ?? '') ?>" />
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="app_identifier"><?php echo _("App Identifier") ?></label>
        <input type="text" id="app_identifier" name="app_identifier" class="settings-form-control" value="<?php echo $this->h($this->provider['app_identifier'] ?? '') ?>" />
        <span class="settings-form-help"><?php echo _("The application ID (e.g. GitHub App ID).") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="installation_id"><?php echo _("Installation ID") ?></label>
        <input type="text" id="installation_id" name="installation_id" class="settings-form-control" value="<?php echo $this->h($this->provider['installation_id'] ?? '') ?>" />
        <span class="settings-form-help"><?php echo _("The installation-scoped identifier.") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="private_key"><?php echo _("Private Key (PEM)") ?></label>
        <?php if (!empty($this->provider['private_key'])): ?>
        <textarea id="private_key" name="private_key" class="settings-form-control" rows="6" placeholder="********"></textarea>
        <label>
          <input type="checkbox" name="_replace_private_key" value="1" />
          <?php echo _("Replace existing key") ?>
        </label>
        <span class="settings-form-help"><?php echo _("A key is saved. Leave empty to keep it. Check the box and paste a new key to replace.") ?></span>
        <?php else: ?>
        <textarea id="private_key" name="private_key" class="settings-form-control" rows="6"></textarea>
        <input type="hidden" name="_replace_private_key" value="1" />
        <?php endif ?>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="token_endpoint"><?php echo _("Token Endpoint") ?></label>
        <input type="url" id="token_endpoint" name="token_endpoint" class="settings-form-control" value="<?php echo $this->h($this->provider['token_endpoint'] ?? '') ?>" />
        <span class="settings-form-help"><?php echo _("Endpoint to exchange JWT assertion for an access token.") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="default_scopes"><?php echo _("Default Scopes") ?></label>
        <input type="text" id="default_scopes" name="default_scopes" class="settings-form-control" value="<?php echo $this->h($this->provider['default_scopes'] ?? '') ?>" />
      </div>

      <div class="settings-form-actions">
        <button type="submit" class="btn btn-primary"><?php echo _("Save") ?></button>
        <a href="<?php echo $this->h($this->baseUrl) ?>/" class="btn btn-secondary"><?php echo _("Cancel") ?></a>
      </div>

    </div>
  </form>
</div>
