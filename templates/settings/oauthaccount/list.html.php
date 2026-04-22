<div class="settings-page">
  <h1 class="settings-page-title"><?php echo _("Connected Accounts") ?></h1>

  <?php if (count($this->providers)): ?>
  <ul class="settings-provider-list">
    <?php foreach ($this->providers as $item): ?>
    <li class="settings-provider-item">
      <div class="settings-provider-info">
        <span class="settings-provider-name"><?php echo $this->h($item['config']['name']) ?></span>
        <span class="settings-provider-type"><?php echo $this->h($item['config']['type']) ?></span>
      </div>

      <?php if ($item['connected']): ?>
        <span class="settings-status settings-status-connected"><?php echo _("Connected") ?></span>
      <?php else: ?>
        <span class="settings-status settings-status-disconnected"><?php echo _("Not connected") ?></span>
      <?php endif ?>

      <div class="settings-provider-actions">
        <?php if ($item['connected']): ?>
          <form method="post" action="<?php echo $this->h($this->baseUrl) ?>/disconnect/<?php echo $this->h($item['config']['provider_id']) ?>">
            <button type="submit" class="btn btn-sm btn-danger"><?php echo _("Disconnect") ?></button>
          </form>
        <?php else: ?>
          <form method="post" action="<?php echo $this->h($this->baseUrl) ?>/connect/<?php echo $this->h($item['config']['provider_id']) ?>">
            <button type="submit" class="btn btn-sm btn-primary"><?php echo _("Connect") ?></button>
          </form>
        <?php endif ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <div class="settings-empty">
    <?php echo _("No OAuth providers are configured. Contact your administrator.") ?>
  </div>
  <?php endif; ?>
</div>
