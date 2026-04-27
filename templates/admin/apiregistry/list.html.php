<div class="settings-page">
  <h1 class="settings-page-title"><?php echo _("API Registry") ?></h1>

  <h2><?php echo _("Legacy APIs (Horde_Registry)") ?></h2>
  <?php if (empty($this->legacyApis)): ?>
    <p><?php echo _("No legacy APIs registered.") ?></p>
  <?php else: ?>
    <table class="horde-table striped sortable">
      <thead>
        <tr>
          <th><?php echo _("API") ?></th>
          <th><?php echo _("Application") ?></th>
          <th><?php echo _("Methods") ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($this->legacyApis as $api): ?>
        <tr>
          <td><?php echo $this->h($api['name']) ?></td>
          <td><?php echo $this->h($api['app']) ?></td>
          <td><?php echo $this->h(implode(', ', $api['methods'])) ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>

  <h2><?php echo _("Modern APIs (ApiRegistry)") ?></h2>
  <?php if (empty($this->modernInterfaces)): ?>
    <p><?php echo _("No modern API providers registered.") ?></p>
  <?php else: ?>
    <table class="horde-table striped sortable">
      <thead>
        <tr>
          <th><?php echo _("Interface") ?></th>
          <th><?php echo _("Provider") ?></th>
          <th><?php echo _("Methods") ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($this->modernInterfaces as $iface): ?>
        <tr>
          <td><?php echo $this->h($iface['name']) ?></td>
          <td><code><?php echo $this->h($iface['provider']) ?></code></td>
          <td>
            <?php if (empty($iface['methods'])): ?>
              <em><?php echo _("none visible") ?></em>
            <?php else: ?>
              <dl class="apiregistry-methods">
                <?php foreach ($iface['methods'] as $method): ?>
                  <dt>
                    <?php echo $this->h($method['name']) ?>
                    <small>(<?php echo $this->h($method['returnType']) ?>)</small>
                    <?php if (!empty($method['permissions'])): ?>
                      <?php foreach ($method['permissions'] as $perm): ?>
                        <span class="settings-status settings-status-warning"><?php echo $this->h($perm) ?></span>
                      <?php endforeach ?>
                    <?php endif ?>
                  </dt>
                  <?php if (!empty($method['description'])): ?>
                    <dd><?php echo $this->h($method['description']) ?></dd>
                  <?php endif ?>
                <?php endforeach ?>
              </dl>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>
