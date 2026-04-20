<h1 class="header"><?php echo _("Connected Accounts") ?></h1>

<?php if (count($this->providers)): ?>
<table class="headerbox striped">
  <thead><tr>
    <th><?php echo _("Provider") ?></th>
    <th><?php echo _("Type") ?></th>
    <th><?php echo _("Status") ?></th>
    <th>&nbsp;</th>
  </tr></thead>
  <tbody>
    <?php foreach ($this->providers as $item): ?>
    <tr>
      <td><?php echo $this->h($item['config']['name']) ?></td>
      <td><?php echo $this->h($item['config']['type']) ?></td>
      <td>
        <?php if ($item['connected']): ?>
          <strong><?php echo _("Connected") ?></strong>
        <?php else: ?>
          <?php echo _("Not connected") ?>
        <?php endif ?>
      </td>
      <td>
        <?php if ($item['connected']): ?>
          <form method="post" action="<?php echo $this->h($this->baseUrl) ?>/disconnect/<?php echo $this->h($item['config']['provider_id']) ?>" style="display:inline">
            <button type="submit"><?php echo _("Disconnect") ?></button>
          </form>
        <?php else: ?>
          <form method="post" action="<?php echo $this->h($this->baseUrl) ?>/connect/<?php echo $this->h($item['config']['provider_id']) ?>" style="display:inline">
            <button type="submit"><?php echo _("Connect") ?></button>
          </form>
        <?php endif ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<p class="headerbox"><em><?php echo _("No OAuth providers are configured. Contact your administrator.") ?></em></p>
<?php endif; ?>
