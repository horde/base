<?php
$asOf = new Horde_Date($this->summary->asOf, 'UTC');
if ($this->timezone) {
    $asOf->setTimezone($this->timezone);
}
$asOfText = Horde\Date\Format::formatDate(
    $asOf->timestamp(),
    $this->dateFormat,
    $this->language
) . ' ' . $asOf->format(
    'HH:mm:ss',
    new Horde\Date\Formatter\IcuFormatter(),
    $this->language
) . ' ' . $asOf->format('T');
?>
<div class="settings-page activesync-health-overview">
<ul class="settings-status-list activesync-health-strip">
  <li class="settings-status-card">
    <div class="settings-status-card-header">
      <span class="settings-status-card-label"><?php echo _("Active") ?></span>
      <span class="settings-status settings-status-ok"><?php echo $this->h($this->summary->active) ?></span>
    </div>
  </li>
  <li class="settings-status-card">
    <div class="settings-status-card-header">
      <span class="settings-status-card-label"><?php echo _("Stuck") ?></span>
      <span class="settings-status <?php echo $this->summary->stuck > 0 ? 'settings-status-error' : 'settings-status-ok' ?>"><?php echo $this->h($this->summary->stuck) ?></span>
    </div>
  </li>
  <li class="settings-status-card">
    <div class="settings-status-card-header">
      <span class="settings-status-card-label"><?php echo _("Wipe pending") ?></span>
      <span class="settings-status <?php echo $this->summary->wipePending > 0 ? 'settings-status-warning' : 'settings-status-na' ?>"><?php echo $this->h($this->summary->wipePending) ?></span>
    </div>
  </li>
  <li class="settings-status-card">
    <div class="settings-status-card-header">
      <span class="settings-status-card-label"><?php echo _("Blocked") ?></span>
      <span class="settings-status <?php echo $this->summary->blocked > 0 ? 'settings-status-warning' : 'settings-status-na' ?>"><?php echo $this->h($this->summary->blocked) ?></span>
    </div>
  </li>
</ul>
<p class="settings-status-card-detail activesync-health-asof">
  <?php echo $this->h(sprintf(_("Health as of %s"), $asOfText)) ?>
  &middot; <code><?php echo $this->h(_("Command line: horde-activesync top")) ?></code>
</p>
</div>
