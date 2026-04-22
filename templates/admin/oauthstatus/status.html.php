<div class="settings-page">
  <h1 class="settings-page-title"><?php echo _("Authentication System Status") ?></h1>

  <ul class="settings-status-list">
    <?php $h = $this->health; ?>

    <li class="settings-status-card">
      <div class="settings-status-card-header">
        <span class="settings-status-card-label"><?php echo _("Provider Backend") ?></span>
        <?php if ($h['backend'] === 'sql'): ?>
          <span class="settings-status settings-status-ok"><?php echo _("SQL") ?></span>
        <?php else: ?>
          <span class="settings-status settings-status-error"><?php echo _("Null") ?></span>
        <?php endif ?>
      </div>
      <?php if ($h['backend'] !== 'sql'): ?>
      <div class="settings-status-card-detail">
        <?php echo sprintf(
            _("Providers cannot be saved. Configure the SQL database in %sHorde Settings%s."),
            '<a href="' . $this->h($this->configUrl) . '">',
            '</a>'
        ) ?>
      </div>
      <?php endif ?>
    </li>

    <li class="settings-status-card">
      <div class="settings-status-card-header">
        <span class="settings-status-card-label"><?php echo _("Secret Encryption") ?></span>
        <?php if ($h['backend'] !== 'sql'): ?>
          <span class="settings-status settings-status-na"><?php echo _("N/A") ?></span>
        <?php elseif ($h['encryption']): ?>
          <span class="settings-status settings-status-ok"><?php echo _("Available") ?></span>
        <?php else: ?>
          <span class="settings-status settings-status-warning"><?php echo _("Unavailable") ?></span>
        <?php endif ?>
      </div>
      <?php if ($h['backend'] === 'sql' && !$h['encryption']): ?>
      <div class="settings-status-card-detail">
        <?php echo sprintf(
            _("Client secrets will be stored unencrypted. Set secret_key in %sHorde Settings%s."),
            '<a href="' . $this->h($this->configUrl) . '">',
            '</a>'
        ) ?>
      </div>
      <?php endif ?>
    </li>

    <li class="settings-status-card">
      <div class="settings-status-card-header">
        <span class="settings-status-card-label"><?php echo _("OAuth2 Signing Key") ?></span>
        <?php if ($h['signing_key']['exists']): ?>
          <span class="settings-status settings-status-ok"><?php echo _("OK") ?></span>
        <?php elseif ($h['signing_key']['configured']): ?>
          <span class="settings-status settings-status-error"><?php echo _("File missing") ?></span>
        <?php else: ?>
          <span class="settings-status settings-status-warning"><?php echo _("Not configured") ?></span>
        <?php endif ?>
      </div>
      <div class="settings-status-card-detail">
        <?php if ($h['signing_key']['exists']): ?>
          <code class="settings-path"><?php echo $this->h($h['signing_key']['path']) ?></code>
        <?php elseif ($h['signing_key']['configured']): ?>
          <code class="settings-path"><?php echo $this->h($h['signing_key']['path']) ?></code>
          <form method="post" action="<?php echo $this->h($this->apiBaseUrl) ?>/signing-key" class="settings-status-card-action">
            <button type="submit" class="btn btn-primary"><?php echo _("Generate Key") ?></button>
          </form>
        <?php else: ?>
          <form method="post" action="<?php echo $this->h($this->apiBaseUrl) ?>/signing-key" class="settings-status-card-action">
            <button type="submit" class="btn btn-primary"><?php echo _("Auto-configure and Generate") ?></button>
          </form>
        <?php endif ?>
      </div>
    </li>

    <li class="settings-status-card">
      <div class="settings-status-card-header">
        <span class="settings-status-card-label"><?php echo _("JWT Session Secret") ?></span>
        <?php if ($h['jwt_secret']['exists']): ?>
          <span class="settings-status settings-status-ok"><?php echo _("OK") ?></span>
        <?php elseif ($h['jwt_secret']['configured']): ?>
          <span class="settings-status settings-status-error"><?php echo _("File missing") ?></span>
        <?php else: ?>
          <span class="settings-status settings-status-warning"><?php echo _("Not configured") ?></span>
        <?php endif ?>
      </div>
      <div class="settings-status-card-detail">
        <?php if ($h['jwt_secret']['exists']): ?>
          <code class="settings-path"><?php echo $this->h($h['jwt_secret']['path']) ?></code>
        <?php elseif ($h['jwt_secret']['configured']): ?>
          <code class="settings-path"><?php echo $this->h($h['jwt_secret']['path']) ?></code>
          <form method="post" action="<?php echo $this->h($this->apiBaseUrl) ?>/jwt-secret" class="settings-status-card-action">
            <button type="submit" class="btn btn-primary"><?php echo _("Generate Secret") ?></button>
          </form>
        <?php else: ?>
          <form method="post" action="<?php echo $this->h($this->apiBaseUrl) ?>/jwt-secret" class="settings-status-card-action">
            <button type="submit" class="btn btn-primary"><?php echo _("Auto-configure and Generate") ?></button>
          </form>
        <?php endif ?>
      </div>
    </li>
  </ul>

  <div class="settings-status-nav">
    <a href="<?php echo $this->h($this->providerUrl) ?>" class="btn btn-primary"><?php echo _("Manage Providers") ?></a>
  </div>
</div>
