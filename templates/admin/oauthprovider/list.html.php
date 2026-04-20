<h1 class="header"><?php echo _("OAuth Providers") ?></h1>

<?php if (count($this->providers)): ?>
<table class="headerbox sortable striped">
  <thead><tr>
    <th><?php echo _("Name") ?></th>
    <th><?php echo _("Type") ?></th>
    <th><?php echo _("Provider ID") ?></th>
    <th><?php echo _("Issuer") ?></th>
    <th><?php echo _("Enabled") ?></th>
    <th>&nbsp;</th>
  </tr></thead>
  <tbody>
    <?php foreach ($this->providers as $provider): ?>
    <tr>
      <td><a href="<?php echo $this->h($this->baseUrl) ?>/<?php echo $this->h($provider['provider_id']) ?>"><?php echo $this->h($provider['name']) ?></a></td>
      <td><?php echo $this->h($provider['type']) ?></td>
      <td><?php echo $this->h($provider['provider_id']) ?></td>
      <td><?php echo $this->h($provider['issuer'] ?? '') ?></td>
      <td><?php echo !empty($provider['enabled']) ? _("Yes") : _("No") ?></td>
      <td>
        <a href="<?php echo $this->h($this->baseUrl) ?>/<?php echo $this->h($provider['provider_id']) ?>"><?php echo _("Edit") ?></a>
        |
        <form method="post" action="<?php echo $this->h($this->baseUrl) ?>/<?php echo $this->h($provider['provider_id']) ?>/delete" style="display:inline">
          <button type="submit" onclick="return confirm('<?php echo _("Are you sure you want to delete this provider?") ?>')"><?php echo _("Delete") ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<p class="headerbox"><em><?php echo _("No OAuth providers configured.") ?></em></p>
<?php endif; ?>

<h2 class="header"><?php echo _("Add Provider") ?></h2>

<form method="post" action="<?php echo $this->h($this->baseUrl) ?>/" class="headerbox">
  <div class="horde-form">
    <div class="horde-form-row">
      <label for="provider_id"><?php echo _("Provider ID (slug)") ?></label>
      <input type="text" id="provider_id" name="provider_id" required pattern="[a-z0-9_-]+" placeholder="google" />
      <span class="horde-form-description"><?php echo _("Lowercase letters, digits, hyphens, underscores only.") ?></span>
    </div>

    <div class="horde-form-row">
      <label for="name"><?php echo _("Display Name") ?></label>
      <input type="text" id="name" name="name" required placeholder="Google" />
    </div>

    <div class="horde-form-row">
      <label for="type"><?php echo _("Type") ?></label>
      <select id="type" name="type" required>
        <option value="oidc"><?php echo _("OpenID Connect (auto-discovery)") ?></option>
        <option value="oauth2"><?php echo _("OAuth 2.0 (manual endpoints)") ?></option>
        <option value="service_app"><?php echo _("Service App (JWT assertion)") ?></option>
      </select>
    </div>

    <div class="horde-form-row">
      <label for="issuer"><?php echo _("Issuer URL") ?></label>
      <input type="url" id="issuer" name="issuer" placeholder="https://accounts.google.com" />
      <span class="horde-form-description"><?php echo _("Required for OIDC. Used for auto-discovery of endpoints.") ?></span>
    </div>

    <div class="horde-form-actions">
      <input type="submit" class="horde-default" value="<?php echo _("Add Provider") ?>" />
    </div>
  </div>
</form>
