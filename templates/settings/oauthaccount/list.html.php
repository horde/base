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

      <div class="settings-provider-status">
        <?php if ($item['bound']): ?>
          <span class="settings-status settings-status-linked"><?php echo _("Linked") ?></span>
          <span class="settings-provider-external">
            <?php if ($item['profile_url']): ?>
              <a href="<?php echo $this->h($item['profile_url']) ?>" target="_blank" rel="noopener"><?php echo $this->h($item['external_name'] ?? $item['external_id']) ?></a>
            <?php else: ?>
              <?php echo $this->h($item['external_name'] ?? $item['external_id']) ?>
            <?php endif ?>
          </span>
        <?php else: ?>
          <span class="settings-status settings-status-unlinked"><?php echo _("Not linked") ?></span>
        <?php endif ?>
      </div>

      <div class="settings-provider-actions">
        <?php if ($item['bound']): ?>
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

  <?php if (!empty($this->superseded)): ?>
  <h2 class="settings-section-title"><?php echo _("Merged Identities") ?></h2>
  <p class="settings-section-desc"><?php echo _("Previously separate identities that have been merged into your account. Resources owned by them may need migration.") ?></p>
  <ul class="settings-superseded-list">
    <?php foreach ($this->superseded as $entry): ?>
    <li class="settings-superseded-item">
      <span class="settings-superseded-role"><?php echo $this->h(_($entry['identity']->role->value)) ?></span>
      <?php if ($entry['identity']->displayName): ?>
        <span class="settings-superseded-name"><?php echo $this->h($entry['identity']->displayName) ?></span>
      <?php endif ?>
      <?php if ($entry['identity']->primaryEmail): ?>
        <span class="settings-superseded-email">&lt;<?php echo $this->h($entry['identity']->primaryEmail) ?>&gt;</span>
      <?php endif ?>
      <?php if (!$entry['identity']->displayName && !$entry['identity']->primaryEmail && empty($entry['links'])): ?>
        <span class="settings-superseded-id"><?php echo $this->h($entry['identity']->id) ?></span>
      <?php endif ?>
      <?php if (!empty($entry['links'])): ?>
      <ul class="settings-superseded-links">
        <?php foreach ($entry['links'] as $link): ?>
          <?php if ($link->provider !== 'local:user'): ?>
          <li>
            <?php echo $this->h($link->provider) ?>:
            <?php echo $this->h($link->externalDisplayName ?? $link->externalId) ?>
          </li>
          <?php endif ?>
        <?php endforeach ?>
      </ul>
      <?php endif ?>
    </li>
    <?php endforeach ?>
  </ul>
  <?php endif ?>
</div>
