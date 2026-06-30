<?php
$grouped = !empty($this->groupByUser);
$entries = $this->entries ?? null;
if ($entries === null) {
    $entries = [];
    foreach ($this->devices as $d_id => $d) {
        $entries[] = [
            'type' => 'device',
            'device' => $d,
            'collections' => $this->collections[$d_id] ?? [],
        ];
    }
}
$adminCols = $this->isAdmin ? ($grouped ? 6 : 7) : 5;
?>
<table class="horde-table activesync-devices striped<?php echo $grouped ? ' activesync-devices-grouped' : '' ?>">
   <tr class="header">
    <?php if ($this->isAdmin && !$grouped):?><th class="smallheader"><?php echo _("User")?></th><?php endif?>
    <th class="smallheader"><?php echo _("Device") ?></th>
    <th class="smallheader"><?php echo _("Last Sync Time") ?></th>
    <th class="smallheader"><?php echo _("Status") ?></th>
    <th class="smallheader"><?php echo _("Device Information") ?></th>
    <?php if ($this->isAdmin):?>
    <th class ="smallheader"><?php echo _("Cached Collections") ?></th>
    <?php endif;?>
    <th class="smallheader"><?php echo _("Actions")?></th>
   </tr>
  <?php foreach ($entries as $entry): ?>
    <?php if ($entry['type'] === 'header'): ?>
    <tr class="activesync-user-header">
      <td colspan="<?php echo $adminCols ?>">
        <span class="activesync-user-header-name"><?php echo $entry['user'] ?></span>
        <span class="activesync-user-header-count"><?php echo sprintf(ngettext('%d device', '%d devices', $entry['count']), $entry['count']) ?></span>
      </td>
    </tr>
    <?php continue; endif; ?>
    <?php $d = $entry['device']; ?>
    <?php $deviceCollections = $entry['collections']; ?>
    <?php if ($d->rwstatus == Horde_ActiveSync::RWSTATUS_PENDING): ?>
      <?php $status = $this->contentTag('span', _("Device wipe pending"), ['class' => 'notice']) ?>
    <?php elseif (!empty($d->accountOnlyRwstatus) && $d->accountOnlyRwstatus == Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING): ?>
      <?php $status = $this->contentTag('span', _("Account wipe pending"), ['class' => 'notice']) ?>
    <?php elseif ($d->rwstatus == Horde_ActiveSync::RWSTATUS_WIPED): ?>
      <?php $status = $this->contentTag('span', _("Device wiped. Remove device state to allow the device to reconnect."), ['class' => 'notice']) ?>
    <?php elseif (!empty($d->accountOnlyRwstatus) && $d->accountOnlyRwstatus == Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_WIPED): ?>
      <?php $status = $this->contentTag('span', _("Account wiped. Remove device state to allow the device to reconnect."), ['class' => 'notice']) ?>
    <?php elseif ($d->blocked):?>
      <?php $status = $this->contentTag('span', _("Device is Blocked."), ['class' => 'notice'])?>
    <?php else: ?>
      <?php $status = $d->policykey ? _("Provisioned") : _("Not Provisioned") ?>
    <?php endif; ?>
    <tr>
      <?php if ($this->isAdmin && !$grouped):?><td><b><?php echo $d->user?></b></td><?php endif?>
      <td><b><?php echo $d->deviceType ?></b></td>
      <?php $lst = $d->getLastSyncTimestamp() ? new Horde_Date($d->getLastSyncTimestamp(), 'UTC') : false; ?>
      <?php if ($lst && $GLOBALS['prefs']->getValue('timezone')): $lst->setTimezone($GLOBALS['prefs']->getValue('timezone')); endif;?>
      <td><?php echo $lst ? Horde\Date\Format::formatDate($lst->timestamp(), $GLOBALS['prefs']->getValue('date_format'), $GLOBALS['language'] ?? 'en_US') . ' ' . $lst->format('HH:mm', new Horde\Date\Formatter\IcuFormatter(), $GLOBALS['language'] ?? 'en_US') . ' ' . $lst->format('T') : _("None") ?></td>
      <td><?php echo $status ?></td>
      <td>
        <?php foreach ($d->getFormattedDeviceProperties() as $key => $value): ?>
          <?php echo '<b>' . $key . '</b>: ' . $value . '<br />' ?>
        <?php endforeach; ?>
        <b><?php echo _("Cached Heartbeat (seconds)")?></b>: <?php echo $d->hbinterval ?><br />
      </td>
      <?php if ($this->isAdmin): ?>
      <td class="activesync-device-collections">
        <?php $collectionCount = count($deviceCollections); ?>
        <?php if (!$collectionCount): ?>
          <em><?php echo _("None") ?></em>
        <?php else: ?>
          <?php
          $classNames = [];
            foreach ($deviceCollections as $cc) {
                if (!empty($cc[_("Class")])) {
                    $classNames[] = $cc[_("Class")];
                }
            }
            $classSummary = implode(', ', array_unique($classNames));
            ?>
          <details class="activesync-collections-details">
            <summary class="activesync-collections-summary">
              <?php echo sprintf(ngettext('%d collection', '%d collections', $collectionCount), $collectionCount) ?>
              <?php if ($classSummary): ?>
                <span class="activesync-collections-preview"> — <?php echo $classSummary ?></span>
              <?php endif; ?>
            </summary>
            <div class="activesync-collections-list">
              <?php foreach ($deviceCollections as $cc): ?>
                <div class="activesync-collection-item">
                  <?php foreach ($cc as $key => $value): ?>
                    <div><b><?php echo $key ?></b>: <?php echo $value ?></div>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
      </td>
      <?php endif; ?>
      <td class="activesync-device-actions">
        <div class="activesync-device-actions-inner">
        <?php if ($d->policykey): ?>
          <input class="horde-delete" type="button" value="<?php echo _("Wipe entire device") ?>" id="wipe_<?php echo $d->id . ':' . $d->user ?>" />
          <?php if (!empty($d->version) && version_compare($d->version, Horde_ActiveSync::VERSION_SIXTEENONE, '>=')): ?>
            <input class="horde-delete" type="button" value="<?php echo _("Wipe account") ?>" id="awipe_<?php echo $d->id . ':' . $d->user ?>" />
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($d->rwstatus == Horde_ActiveSync::RWSTATUS_PENDING
              || (!empty($d->accountOnlyRwstatus)
                  && $d->accountOnlyRwstatus == Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING)): ?>
          <input type="button" value="<?php echo _("Cancel wipe") ?>" id="cancel_<?php echo $d->id . ':' . $d->user?>" />
        <?php endif; ?>
        <input class="horde-delete" type="button" value="<?php echo _("Remove device state") ?>" id="remove_<?php echo $d->id . ':' . $d->user ?>" />
        <?php if ($d->blocked && $this->isAdmin): ?>
          <input class="horde-button" type="button" value="<?php echo _("Unblock")?>" id="unblock_<?php echo $d->id . ':' . $d->user ?>" />
        <?php elseif ($this->isAdmin): ?>
          <input class="horde-delete" type="button" value="<?php echo _("Block")?>" id="block_<?php echo $d->id . ':' . $d->user ?>" />
        <?php endif; ?>
        </div>
      </td>
   </tr>
  <?php endforeach; ?>
  </table>
