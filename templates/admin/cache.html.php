<h1 class="header">
 <?php echo _("Cache Administration") ?>
</h1>

<div class="horde-content">
 <p>
  <?php echo _("Driver") ?>: <strong><?php echo $this->h($this->driver) ?></strong>
 </p>
 <p>
  <?php echo _("Backend Read/Write Test") ?>:
<?php if ($this->rw): ?>
  <strong><?php echo _("Success") ?></strong>
<?php else: ?>
  <strong class="cacheAdminError"><?php echo _("FAIL") ?></strong>
  (<?php echo _("Check your cache settings in the Horde configuration.") ?>)
<?php endif; ?>
 </p>

<?php if (isset($this->css_enabled) && $this->css_enabled): ?>
 <h2><?php echo _("CSS Cache") ?></h2>
<?php if (isset($this->static_dir)): ?>
 <p>
  <?php echo _("Static Directory") ?>: <code><?php echo $this->h($this->static_dir) ?></code>
 </p>
<?php endif; ?>
 <p>
  <?php echo _("Cached CSS Files") ?>: <strong><?php echo $this->css_count ?></strong>
 </p>
 <p>
  <?php echo _("Total Size") ?>: <strong><?php
    $bytes = $this->css_size;
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    echo number_format($bytes, 2) . ' ' . $units[$i];
    ?></strong>
 </p>
<?php elseif (isset($this->css_enabled)): ?>
 <h2><?php echo _("CSS Cache") ?></h2>
 <p class="cacheAdminError">
  <?php echo _("CSS caching is enabled but no cached files found.") ?>
 </p>
<?php endif; ?>
</div>

<?php if ($this->rw): ?>
<form name="cache" action="<?php echo $this->action ?>" method="post">
 <p class="horde-form-buttons">
  <input type="submit" class="horde-default" name="clearcache" value="<?php echo _("Clear Cache") ?>" />
  <input type="submit" class="horde-button" name="purgecss" value="<?php echo _("Purge CSS Cache") ?>" />
 </p>
</form>
<?php endif; ?>
