<?php
/**
 * Perms add-child form template.
 *
 * Rendered by Horde\Core\Perms\Ui::renderForm() when the form mode is
 * 'add'. Expects these view vars from setupAddForm():
 *
 *   $this->parent_title       Title of the parent permission.
 *   $this->perm_id            Numeric id of the parent permission.
 *   $this->child_perms        false when no children can be added,
 *                             otherwise a name => label hash of the
 *                             available child permissions.
 *   $this->force_choice       When non-null, forces this exact child
 *                             name and locks the picker.
 *   $this->existing_children  List of already-added child names, used
 *                             to filter the dropdown when the caller
 *                             did not force a choice.
 *   $this->action             Form action URL.
 *   $this->formToken          CSRF token string.
 */

$available = $this->child_perms;
if (is_array($available) && $this->force_choice === null) {
    foreach ($this->existing_children as $existing) {
        unset($available[$existing]);
    }
}
?>
<h1 class="header">
 <?php echo sprintf(_("Add a child permission to &ldquo;%s&rdquo;"), $this->h($this->parent_title)) ?>
</h1>

<div class="horde-content">
<form class="perms-form perms-form-add" method="post" action="<?php echo $this->h($this->action) ?>">
 <input type="hidden" name="perm_id" value="<?php echo $this->h((string) $this->perm_id) ?>" />
 <input type="hidden" name="horde_form_token" value="<?php echo $this->h($this->formToken) ?>" />

 <?php if ($available === false): ?>
  <p class="perms-empty"><?php echo _("No children can be added to this permission.") ?></p>
 <?php elseif ($this->force_choice !== null): ?>
  <input type="hidden" name="child" value="<?php echo $this->h($this->force_choice) ?>" />
  <p>
   <?php echo sprintf(
       _("Add child permission &ldquo;%s&rdquo;?"),
       $this->h((string) ($available[$this->force_choice] ?? $this->force_choice))
   ) ?>
  </p>
  <p class="perms-buttons horde-form-buttons">
   <input type="submit" class="horde-default" value="<?php echo $this->h(_("Add")) ?>" />
  </p>
 <?php else: ?>
  <p>
   <label>
    <?php echo _("Permission") ?>
    <select name="child" required="required">
     <option value=""><?php echo _("-- select --") ?></option>
     <?php foreach ($available as $childName => $childLabel): ?>
      <option value="<?php echo $this->h((string) $childName) ?>"><?php echo $this->h((string) $childLabel) ?></option>
     <?php endforeach; ?>
    </select>
   </label>
  </p>
  <p class="perms-buttons horde-form-buttons">
   <input type="submit" class="horde-default" value="<?php echo $this->h(_("Add")) ?>" />
  </p>
 <?php endif; ?>
</form>
</div>
