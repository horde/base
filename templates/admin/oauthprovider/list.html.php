<div class="settings-page">
  <h1 class="settings-page-title"><?php echo _("OAuth Providers") ?></h1>

  <div class="settings-status-nav">
    <a href="<?php echo $this->h($this->statusUrl) ?>" class="btn btn-secondary"><?php echo _("System Status") ?></a>
  </div>

  <?php if (!empty($this->storageUnavailable)): ?>
  <div class="settings-empty">
    <?php echo sprintf(
        _("Providers cannot be saved. Configure the SQL database in %sHorde Settings%s."),
        '<a href="' . $this->h($this->configUrl) . '">',
        '</a>'
    ) ?>
  </div>
  <?php endif; ?>

  <?php if (count($this->providers)): ?>
  <ul class="settings-provider-list">
    <?php foreach ($this->providers as $provider): ?>
    <li class="settings-provider-item">
      <div class="settings-provider-info">
        <span class="settings-provider-name">
          <a href="<?php echo $this->h($this->baseUrl) ?>/<?php echo $this->h($provider['provider_id']) ?>"><?php echo $this->h($provider['name']) ?></a>
        </span>
        <span class="settings-provider-type"><?php echo $this->h($provider['type']) ?> &mdash; <?php echo $this->h($provider['provider_id']) ?></span>
      </div>

      <?php if (!empty($provider['enabled'])): ?>
        <span class="settings-status settings-status-connected"><?php echo _("Enabled") ?></span>
      <?php else: ?>
        <span class="settings-status settings-status-disconnected"><?php echo _("Disabled") ?></span>
      <?php endif ?>

      <div class="settings-provider-actions">
        <a href="<?php echo $this->h($this->baseUrl) ?>/<?php echo $this->h($provider['provider_id']) ?>" class="btn btn-primary btn-sm"><?php echo _("Edit") ?></a>
        <form method="post" action="<?php echo $this->h($this->baseUrl) ?>/<?php echo $this->h($provider['provider_id']) ?>/delete">
          <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('<?php echo _("Are you sure you want to delete this provider?") ?>')"><?php echo _("Delete") ?></button>
        </form>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <div class="settings-empty">
    <?php echo _("No OAuth providers configured.") ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($this->presets)): ?>
  <h2 class="settings-section-title"><?php echo _("Quick Setup") ?></h2>
  <p class="settings-section-desc"><?php echo _("Select a provider to create it with pre-configured endpoints. You only need to add your Client ID and Client Secret.") ?></p>

  <ul class="settings-provider-list">
    <?php foreach ($this->presets as $presetId => $preset): ?>
    <li class="settings-provider-item">
      <div class="settings-provider-info">
        <span class="settings-provider-name">
          <?php if (!empty($preset['display']['color'])): ?>
          <span class="settings-preset-swatch" style="background-color:<?php echo $this->h($preset['display']['color']) ?>"></span>
          <?php endif ?>
          <?php echo $this->h($preset['name']) ?>
        </span>
        <span class="settings-provider-type"><?php echo $this->h($preset['type']) ?></span>
      </div>

      <?php if (!empty($preset['notes'])): ?>
      <span class="settings-provider-type"><?php echo $this->h($preset['notes']) ?></span>
      <?php endif ?>

      <div class="settings-provider-actions">
        <form method="post" action="<?php echo $this->h($this->baseUrl) ?>/">
          <input type="hidden" name="_preset" value="<?php echo $this->h($presetId) ?>" />
          <button type="submit" class="btn btn-primary btn-sm"><?php echo _("Set Up") ?></button>
        </form>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <h2 class="settings-section-title"><?php echo _("Add Provider Manually") ?></h2>

  <form method="post" action="<?php echo $this->h($this->baseUrl) ?>/">
    <div class="settings-form-card">
      <div class="settings-form-row">
        <label class="settings-form-label" for="provider_id"><?php echo _("Provider ID (slug)") ?></label>
        <input type="text" id="provider_id" name="provider_id" class="settings-form-control" required pattern="[a-z0-9_-]+" placeholder="google" />
        <span class="settings-form-help"><?php echo _("Lowercase letters, digits, hyphens, underscores only.") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="name"><?php echo _("Display Name") ?></label>
        <input type="text" id="name" name="name" class="settings-form-control" required placeholder="Google" />
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="type"><?php echo _("Type") ?></label>
        <select id="type" name="type" class="settings-form-control" required>
          <option value="oidc"><?php echo _("OpenID Connect (auto-discovery)") ?></option>
          <option value="oauth2"><?php echo _("OAuth 2.0 (manual endpoints)") ?></option>
          <option value="service_app"><?php echo _("Service App (JWT assertion)") ?></option>
        </select>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="issuer"><?php echo _("Issuer URL") ?></label>
        <input type="url" id="issuer" name="issuer" class="settings-form-control" placeholder="https://accounts.google.com" />
        <span class="settings-form-help"><?php echo _("Required for OIDC. Used for auto-discovery of endpoints.") ?></span>
      </div>

      <div class="settings-form-actions">
        <button type="submit" class="btn btn-primary"><?php echo _("Add Provider") ?></button>
      </div>
    </div>
  </form>
</div>
