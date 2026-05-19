<div class="settings-page">
  <h1 class="settings-page-title"><?php echo _("ActiveSync Administration") ?></h1>
  <?php $s = $this->status; ?>
  <?php $allOk = $s['package'] && $s['enabled'] && $s['storage'] && $s['database'] !== false && !is_string($s['database']); ?>
  <?php if (!$allOk): ?>
  <p><?php echo _("ActiveSync is not fully operational. The following diagnostics show what is missing.") ?></p>
  <?php endif ?>

  <ul class="settings-status-list">

    <li class="settings-status-card">
      <div class="settings-status-card-header">
        <span class="settings-status-card-label"><?php echo _("ActiveSync Package") ?></span>
        <?php if ($s['package']): ?>
          <span class="settings-status settings-status-ok"><?php echo _("Installed") ?></span>
        <?php else: ?>
          <span class="settings-status settings-status-error"><?php echo _("Missing") ?></span>
        <?php endif ?>
      </div>
      <?php if (!$s['package']): ?>
      <div class="settings-status-card-detail">
        <?php echo _("The horde/activesync package is not installed. Install it via:") ?>
        <code class="settings-path">composer require horde/activesync</code>
      </div>
      <?php endif ?>
    </li>

    <li class="settings-status-card">
      <div class="settings-status-card-header">
        <span class="settings-status-card-label"><?php echo _("Configuration") ?></span>
        <?php if ($s['enabled']): ?>
          <span class="settings-status settings-status-ok"><?php echo _("Enabled") ?></span>
        <?php else: ?>
          <span class="settings-status settings-status-error"><?php echo _("Disabled") ?></span>
        <?php endif ?>
      </div>
      <?php if (!$s['enabled']): ?>
      <div class="settings-status-card-detail">
        <?php echo sprintf(
            _("ActiveSync is not enabled. Enable it in %sHorde Settings%s under the ActiveSync tab."),
            '<a href="' . $this->h($this->configUrl) . '">',
            '</a>'
        ) ?>
      </div>
      <?php endif ?>
    </li>

    <li class="settings-status-card">
      <div class="settings-status-card-header">
        <span class="settings-status-card-label"><?php echo _("Storage Backend") ?></span>
        <?php if ($s['storage']): ?>
          <span class="settings-status settings-status-ok"><?php echo $this->h($s['storage']) ?></span>
        <?php elseif ($s['enabled']): ?>
          <span class="settings-status settings-status-warning"><?php echo _("Not configured") ?></span>
        <?php else: ?>
          <span class="settings-status settings-status-na"><?php echo _("N/A") ?></span>
        <?php endif ?>
      </div>
      <?php if ($s['enabled'] && !$s['storage']): ?>
      <div class="settings-status-card-detail">
        <?php echo sprintf(
            _("No storage backend configured. Configure it in %sHorde Settings%s under the ActiveSync tab."),
            '<a href="' . $this->h($this->configUrl) . '">',
            '</a>'
        ) ?>
      </div>
      <?php endif ?>
    </li>

    <li class="settings-status-card">
      <div class="settings-status-card-header">
        <span class="settings-status-card-label"><?php echo _("Database Connection") ?></span>
        <?php if ($s['database'] === null): ?>
          <span class="settings-status settings-status-na"><?php echo _("N/A") ?></span>
        <?php elseif ($s['database'] === true): ?>
          <span class="settings-status settings-status-ok"><?php echo _("OK") ?></span>
        <?php else: ?>
          <span class="settings-status settings-status-error"><?php echo _("Failed") ?></span>
        <?php endif ?>
      </div>
      <?php if (is_string($s['database'])): ?>
      <div class="settings-status-card-detail">
        <?php echo $this->h($s['database']) ?>
      </div>
      <?php elseif ($s['database'] === null && !$s['enabled']): ?>
      <div class="settings-status-card-detail">
        <?php echo _("Enable ActiveSync first to test database connectivity.") ?>
      </div>
      <?php endif ?>
    </li>
  </ul>

  <div class="settings-status-card-detail" style="margin-top: 1em;">
    <strong><?php echo _("Web Server Requirement:") ?></strong>
    <?php echo _("ActiveSync requires a web server alias mapping /Microsoft-Server-ActiveSync to /horde/rpc.php.") ?>
  </div>
</div>
