<div class="settings-page">
  <h1 class="settings-page-title"><?php echo _("API Registry") ?></h1>

  <h2><?php echo _("Modern APIs (ApiRegistry)") ?></h2>
  <?php if (empty($this->modernInterfaces)): ?>
    <p><?php echo _("No modern API providers registered.") ?></p>
  <?php else: ?>
    <ul class="settings-status-list">
      <?php foreach ($this->modernInterfaces as $iface): ?>
        <li class="settings-status-card">
          <div class="settings-status-card-header">
            <span class="settings-status-card-label"><?php echo $this->h($iface['name']) ?></span>
            <span class="settings-status settings-status-ok"><?php echo count($iface['methods']) ?> <?php echo _("methods") ?></span>
          </div>
          <div class="settings-status-card-detail">
            <code><?php echo $this->h($iface['provider']) ?></code>
            <?php if (!empty($iface['methods'])): ?>
              <table class="horde-table striped" style="margin-top: 0.5em">
                <thead>
                  <tr>
                    <th><?php echo _("Method") ?></th>
                    <th><?php echo _("Return") ?></th>
                    <th><?php echo _("Description") ?></th>
                    <th><?php echo _("Permissions") ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($iface['methods'] as $method): ?>
                  <tr>
                    <td><strong><?php echo $this->h($method['name']) ?></strong></td>
                    <td><code><?php echo $this->h($method['returnType']) ?></code></td>
                    <td><?php echo $this->h($method['description']) ?></td>
                    <td>
                      <?php if (!empty($method['permissions'])): ?>
                        <?php foreach ($method['permissions'] as $perm): ?>
                          <span class="settings-status settings-status-warning"><?php echo $this->h($perm) ?></span>
                        <?php endforeach ?>
                      <?php endif ?>
                    </td>
                  </tr>
                  <?php endforeach ?>
                </tbody>
              </table>
            <?php else: ?>
              <em><?php echo _("No methods visible without context") ?></em>
            <?php endif ?>
          </div>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>

  <h2><?php echo _("Legacy APIs (Horde_Registry)") ?></h2>
  <?php if (empty($this->legacyApis)): ?>
    <p><?php echo _("No legacy APIs registered.") ?></p>
  <?php else: ?>
    <ul class="settings-status-list">
      <?php foreach ($this->legacyApis as $api): ?>
        <li class="settings-status-card">
          <div class="settings-status-card-header">
            <span class="settings-status-card-label"><?php echo $this->h($api['name']) ?></span>
            <?php if ($api['app']): ?>
              <span class="settings-status settings-status-na"><?php echo $this->h($api['app']) ?></span>
            <?php endif ?>
          </div>
          <?php if (!empty($api['methods'])): ?>
            <div class="settings-status-card-detail">
              <?php echo $this->h(implode(', ', $api['methods'])) ?>
            </div>
          <?php endif ?>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</div>
