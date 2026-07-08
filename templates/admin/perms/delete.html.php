<?php
/**
 * Perms delete-confirmation template.
 *
 * Rendered by Horde\Core\Perms\Ui::renderForm() when the form mode is
 * 'delete'. Expects these view vars from setupDeleteForm():
 *
 *   $this->title    Human-readable permission title.
 *   $this->perm_id  Numeric permission id.
 *   $this->action   Form action URL.
 *   $this->formToken  CSRF token string.
 *
 * Two submit buttons post the same form. validateDeleteForm() reads
 * the submitbutton value: 'delete' confirms, 'cancel' aborts.
 */
?>
<h1 class="header">
 <?php echo sprintf(_("Delete permissions for &ldquo;%s&rdquo;"), $this->h($this->title)) ?>
</h1>

<div class="horde-content">
<form class="perms-form perms-form-delete" method="post" action="<?php echo $this->h($this->action) ?>">
 <input type="hidden" name="perm_id" value="<?php echo $this->h((string) $this->perm_id) ?>" />
 <input type="hidden" name="horde_form_token" value="<?php echo $this->h($this->formToken) ?>" />

 <p>
  <?php echo sprintf(
      _("Delete permissions for &ldquo;%s&rdquo; and any sub-permissions?"),
      $this->h($this->title)
  ) ?>
 </p>

 <p class="perms-buttons horde-form-buttons">
  <input type="submit" name="submitbutton" class="horde-delete" value="<?php echo $this->h(_("Delete")) ?>" />
  <input type="submit" name="submitbutton" class="horde-cancel" value="<?php echo $this->h(_("Do not delete")) ?>" />
 </p>
</form>
</div>
