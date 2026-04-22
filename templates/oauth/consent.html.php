<h1 class="header"><?php echo _("Authorize Application") ?></h1>

<div class="headerbox">
  <p>
    <?php echo sprintf(
        _("The application %s is requesting access to your account."),
        '<strong>' . $this->h($this->clientName) . '</strong>'
    ) ?>
  </p>

  <?php if (!empty($this->scopes)): ?>
  <h2><?php echo _("Requested Permissions") ?></h2>
  <ul>
    <?php foreach ($this->scopes as $scope): ?>
    <li><?php echo $this->h($scope) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <form method="post" class="horde-form">
    <input type="hidden" name="csrf_token" value="<?php echo $this->h($this->csrfToken) ?>" />

    <div class="horde-form-actions">
      <button type="submit" name="decision" value="approve" class="horde-default">
        <?php echo _("Allow") ?>
      </button>
      <button type="submit" name="decision" value="deny">
        <?php echo _("Deny") ?>
      </button>
    </div>
  </form>
</div>
