<div class="settings-page">
  <h1 class="settings-page-title"><?php echo sprintf(_("Edit Provider: %s"), $this->h($this->provider['name'])) ?></h1>

  <?php if ($this->setupNotes !== '' || empty($this->provider['client_id'])): ?>
  <div class="settings-callout">
    <strong><?php echo _("Setup") ?></strong>
    <?php if ($this->setupNotes !== ''): ?>
    <p><?php echo $this->h($this->setupNotes) ?></p>
    <?php endif ?>
    <p>
      <?php echo _("Set this as your Redirect URI in the provider's console:") ?>
      <code class="settings-path"><?php echo $this->h($this->callbackUrl) ?></code>
    </p>
    <?php if (empty($this->provider['client_id'])): ?>
    <p class="settings-callout-warning"><?php echo _("Enter your Client ID and Client Secret below to enable this provider.") ?></p>
    <?php endif ?>
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
        <span class="settings-form-static"><?php echo $this->h($this->provider['type']) ?></span>
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

      <?php if ($this->provider['type'] === 'oidc'): ?>
      <div class="settings-form-row">
        <button type="submit" name="_action" value="discover" class="btn btn-secondary"><?php echo _("Auto-discover endpoints from issuer") ?></button>
      </div>
      <?php endif ?>

      <h3 class="settings-section-title"><?php echo _("Credentials") ?></h3>

      <div class="settings-form-row">
        <label class="settings-form-label" for="client_id"><?php echo _("Client ID") ?></label>
        <input type="text" id="client_id" name="client_id" class="settings-form-control" required value="<?php echo $this->h($this->provider['client_id'] ?? '') ?>" />
        <span class="settings-form-help"><?php echo _("From your provider's developer console.") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="client_secret"><?php echo _("Client Secret") ?></label>
        <?php if (!empty($this->provider['client_secret'])): ?>
        <input type="password" id="client_secret" name="client_secret" class="settings-form-control" value="" placeholder="********" />
        <label>
          <input type="checkbox" name="_replace_client_secret" value="1" />
          <?php echo _("Replace existing secret") ?>
        </label>
        <span class="settings-form-help"><?php echo _("A secret is saved. Leave empty to keep it. Check the box and enter a new value to replace.") ?></span>
        <?php else: ?>
        <input type="password" id="client_secret" name="client_secret" class="settings-form-control" value="" />
        <input type="hidden" name="_replace_client_secret" value="1" />
        <span class="settings-form-help"><?php echo _("From your provider's developer console.") ?></span>
        <?php endif ?>
      </div>

      <h3 class="settings-section-title"><?php echo _("Endpoints") ?></h3>
      <p class="settings-section-desc"><?php echo _("Pre-configured from preset. Only change if your provider uses non-standard URLs.") ?></p>

      <div class="settings-form-row">
        <label class="settings-form-label" for="authorization_endpoint"><?php echo _("Authorization Endpoint") ?></label>
        <input type="url" id="authorization_endpoint" name="authorization_endpoint" class="settings-form-control" value="<?php echo $this->h($this->provider['authorization_endpoint'] ?? '') ?>" />
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="token_endpoint"><?php echo _("Token Endpoint") ?></label>
        <input type="url" id="token_endpoint" name="token_endpoint" class="settings-form-control" value="<?php echo $this->h($this->provider['token_endpoint'] ?? '') ?>" />
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="userinfo_endpoint"><?php echo _("Userinfo Endpoint") ?></label>
        <input type="url" id="userinfo_endpoint" name="userinfo_endpoint" class="settings-form-control" value="<?php echo $this->h($this->provider['userinfo_endpoint'] ?? '') ?>" />
      </div>

      <?php if ($this->provider['type'] === 'oidc'): ?>
      <div class="settings-form-row">
        <label class="settings-form-label" for="jwks_uri"><?php echo _("JWKS URI") ?></label>
        <input type="url" id="jwks_uri" name="jwks_uri" class="settings-form-control" value="<?php echo $this->h($this->provider['jwks_uri'] ?? '') ?>" />
      </div>
      <?php endif ?>

      <div class="settings-form-row">
        <label class="settings-form-label" for="revocation_endpoint"><?php echo _("Revocation Endpoint") ?></label>
        <input type="url" id="revocation_endpoint" name="revocation_endpoint" class="settings-form-control" value="<?php echo $this->h($this->provider['revocation_endpoint'] ?? '') ?>" />
      </div>

      <?php if ($this->provider['type'] === 'oidc'): ?>
      <div class="settings-form-row">
        <label class="settings-form-label" for="introspection_endpoint"><?php echo _("Introspection Endpoint") ?></label>
        <input type="url" id="introspection_endpoint" name="introspection_endpoint" class="settings-form-control" value="<?php echo $this->h($this->provider['introspection_endpoint'] ?? '') ?>" />
      </div>
      <?php endif ?>

      <div class="settings-form-row">
        <label class="settings-form-label" for="default_scopes"><?php echo _("Default Scopes") ?></label>
        <input type="text" id="default_scopes" name="default_scopes" class="settings-form-control" value="<?php echo $this->h($this->provider['default_scopes'] ?? '') ?>" placeholder="openid email profile" />
      </div>

      <div class="settings-form-row">
        <span class="settings-form-label"><?php echo _("Redirect URI") ?></span>
        <code class="settings-path"><?php echo $this->h($this->callbackUrl) ?></code>
        <span class="settings-form-help"><?php echo _("This is computed automatically. Copy this value into your provider's console.") ?></span>
      </div>

<?php if ($this->provider['type'] === 'oidc'): ?>
      <h3 class="settings-section-title"><?php echo _("Logout / Single Log-Out") ?></h3>

      <div class="settings-form-row">
        <label class="settings-form-label" for="logout_type"><?php echo _("Logout Strategy") ?></label>
        <select id="logout_type" name="logout_type" class="settings-form-control">
          <?php foreach (['local' => _("Local only"), 'slo' => _("SLO redirect"), 'revoke_and_slo' => _("Revoke tokens + SLO redirect")] as $val => $label): ?>
          <option value="<?php echo $val ?>"<?php if (($this->provider['logout_type'] ?? 'local') === $val): ?> selected<?php endif ?>><?php echo $label ?></option>
          <?php endforeach ?>
        </select>
        <span class="settings-form-help"><?php echo _("Local: clear tokens locally only. SLO: also redirect to provider end_session_endpoint. Revoke: also call revocation endpoint before redirect.") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="end_session_endpoint"><?php echo _("End Session Endpoint") ?></label>
        <input type="url" id="end_session_endpoint" name="end_session_endpoint" class="settings-form-control" value="<?php echo $this->h($this->provider['end_session_endpoint'] ?? '') ?>" />
        <span class="settings-form-help"><?php echo _("Leave empty to use auto-discovery. Required for SLO strategies.") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="post_logout_redirect_uri"><?php echo _("Post-Logout Redirect URI") ?></label>
        <input type="url" id="post_logout_redirect_uri" name="post_logout_redirect_uri" class="settings-form-control" value="<?php echo $this->h($this->provider['post_logout_redirect_uri'] ?? '') ?>" />
        <span class="settings-form-help"><?php echo _("Where the provider should redirect after SLO. Defaults to Horde portal if empty.") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="backchannel_username_claim"><?php echo _("Back-Channel Username Claim") ?></label>
        <input type="text" id="backchannel_username_claim" name="backchannel_username_claim" class="settings-form-control" value="<?php echo $this->h($this->provider['backchannel_username_claim'] ?? 'sub') ?>" placeholder="sub" />
        <span class="settings-form-help"><?php echo _("JWT claim used to identify the user in back-channel logout tokens. Usually 'sub'.") ?></span>
      </div>

      <h3 class="settings-section-title"><?php echo _("XOAUTH2 / Mail Authentication") ?></h3>

      <div class="settings-form-row">
        <label class="settings-form-label" for="xoauth2_use_email"><?php echo _("Use email address as XOAUTH2 username") ?></label>
        <select id="xoauth2_use_email" name="xoauth2_use_email" class="settings-form-control">
          <option value="0"<?php if (empty($this->provider['xoauth2_use_email'])): ?> selected<?php endif ?>><?php echo _("No — use Horde username as-is") ?></option>
          <option value="1"<?php if (!empty($this->provider['xoauth2_use_email'])): ?> selected<?php endif ?>><?php echo _("Yes — append domain below") ?></option>
        </select>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="xoauth2_domain"><?php echo _("XOAUTH2 Domain") ?></label>
        <input type="text" id="xoauth2_domain" name="xoauth2_domain" class="settings-form-control" value="<?php echo $this->h($this->provider['xoauth2_domain'] ?? '') ?>" placeholder="example.com" />
        <span class="settings-form-help"><?php echo _("Domain appended to the username for XOAUTH2 (e.g. 'jdoe' → 'jdoe@example.com'). Only used if the option above is enabled.") ?></span>
      </div>
      <?php endif ?>

      <h3 class="settings-section-title"><?php echo _("Appearance") ?></h3>

      <div class="settings-form-row">
        <label class="settings-form-label" for="display_label"><?php echo _("Button Label") ?></label>
        <input type="text" id="display_label" name="display_label" class="settings-form-control" value="<?php echo $this->h($this->provider['display_label'] ?? '') ?>" placeholder="<?php echo $this->h($this->provider['name']) ?>" />
        <span class="settings-form-help"><?php echo _("Text shown on the login button. Defaults to provider name if empty.") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="display_icon"><?php echo _("Icon") ?></label>
        <input type="text" id="display_icon" name="display_icon" class="settings-form-control" value="<?php echo $this->h($this->provider['display_icon'] ?? '') ?>" placeholder="github" />
        <span class="settings-form-help"><?php echo _("Icon identifier used by the login page theme.") ?></span>
      </div>

      <div class="settings-form-row">
        <label class="settings-form-label" for="display_color"><?php echo _("Brand Color") ?></label>
        <input type="text" id="display_color" name="display_color" class="settings-form-control" value="<?php echo $this->h($this->provider['display_color'] ?? '') ?>" placeholder="#24292e" />
        <?php if (!empty($this->provider['display_color'])): ?>
        <span class="settings-color-swatch" style="background-color:<?php echo $this->h($this->provider['display_color']) ?>"></span>
        <?php endif ?>
        <span class="settings-form-help"><?php echo _("Hex color for the login button background.") ?></span>
      </div>

      <div class="settings-form-actions">
        <button type="submit" class="btn btn-primary"><?php echo _("Save") ?></button>
        <a href="<?php echo $this->h($this->baseUrl) ?>/" class="btn btn-secondary"><?php echo _("Cancel") ?></a>
      </div>

    </div>
  </form>
</div>
