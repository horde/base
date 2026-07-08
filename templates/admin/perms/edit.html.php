<?php
/**
 * Perms edit form template (negative-permissions dialog).
 *
 * Rendered by Horde\Core\Perms\Ui::renderForm(). Expects these view
 * vars from Horde\Core\Perms\Ui::setupEditForm():
 *
 *   $this->title                  Human-readable permission title.
 *   $this->perm_id                Numeric permission id.
 *   $this->type                   'matrix' | 'boolean' | other.
 *   $this->cols                   Horde_Perms::getPermsArray() output.
 *   $this->default_grant / _deny  Scope-level masks or nulls.
 *   $this->guest_grant            Grant-only (guests are disjunct).
 *   $this->creator_grant / _deny  Scope-level masks or nulls.
 *   $this->user_grants / _denies  name => mask hashes.
 *   $this->user_list              user_id => label (may be empty).
 *   $this->new_users              user_id => label of users not yet
 *                                 assigned (may be empty).
 *   $this->group_grants / _denies name => mask hashes.
 *   $this->group_list             group_id => label (may be empty).
 *   $this->new_groups             group_id => label of unassigned groups.
 *   $this->action                 Form action URL.
 *   $this->formToken              CSRF token string.
 *
 * The rendered HTML uses semantic classes prefixed with "perms-" so the
 * companion stylesheet and JS (perms-tristate.css / perms-tristate.js
 * in horde/Core) can hook without depending on markup order.
 */
?>
<h1 class="header">
 <?php echo sprintf(_("Permissions for &ldquo;%s&rdquo;"), $this->h($this->title)) ?>
</h1>

<div class="horde-content">
<form class="perms-form" method="post" action="<?php echo $this->h($this->action) ?>" data-perm-type="<?php echo $this->h($this->type) ?>">
 <input type="hidden" name="perm_id" value="<?php echo $this->h((string) $this->perm_id) ?>" />
 <input type="hidden" name="perm_type" value="<?php echo $this->h($this->type) ?>" />
 <input type="hidden" name="horde_form_token" value="<?php echo $this->h($this->formToken) ?>" />

 <div class="perms-explainer horde-content">
  <p><?php echo _("Each cell has three states:") ?></p>
  <ul class="perms-explainer-states">
   <li><span class="perms-verdict perms-verdict-grant">✓</span> <?php echo _("Grant. Add this permission at this scope.") ?></li>
   <li><span class="perms-verdict perms-verdict-neutral">−</span> <?php echo _("No rule. This scope does not affect the permission.") ?></li>
   <li><span class="perms-verdict perms-verdict-deny">✕</span> <?php echo _("Deny. Remove this permission at this scope. A more specific grant can still restore it.") ?></li>
  </ul>
 </div>

 <?php // ------- Guest (disjunct, grant only, evaluated first) ------- ?>
 <section class="perms-scope perms-scope-guest" data-scope="guest">
  <h2 class="perms-scope-header"><?php echo _("Guests (not logged in)") ?></h2>
  <p class="perms-scope-help"><?php echo _("Applies only to users who are not logged in. Guests are resolved independently of the cascade below, so denies do not apply here.") ?></p>
  <?php echo $this->renderPartial('grant_scope', ['locals' => [
      'scope' => 'guest',
      'grant' => $this->guest_grant,
      'cols' => $this->cols,
      'type' => $this->type,
  ]]) ?>
 </section>

 <?php // ------- Cascade for authenticated users (broadest -> narrowest) ------- ?>
 <div class="perms-cascade">
  <p class="perms-cascade-legend"><?php echo _("The scopes below layer from broadest to narrowest. A rule at a more specific scope beats a less specific one. A grant at a more specific scope can restore a bit denied above it; a deny at a more specific scope can remove a bit granted above it.") ?></p>

  <?php // ------- Default (All Authenticated Users): baseline ------- ?>
  <section class="perms-scope perms-scope-cascade" data-scope="default" data-cascade-step="1">
   <h2 class="perms-scope-header">
    <span class="perms-scope-step">1.</span>
    <?php echo _("All Authenticated Users") ?>
   </h2>
   <p class="perms-scope-help"><?php echo _("Baseline that applies to every logged-in user unless overridden below.") ?></p>
   <?php echo $this->renderPartial('tri_scope', ['locals' => [
       'scope' => 'default',
       'label_grant' => $this->default_grant,
       'label_deny' => $this->default_deny,
       'cols' => $this->cols,
       'type' => $this->type,
   ]]) ?>
  </section>

  <?php // ------- Groups: layered on top for members ------- ?>
  <section class="perms-scope perms-scope-cascade perms-scope-list" data-scope="g" data-cascade-step="2">
   <h2 class="perms-scope-header">
    <span class="perms-scope-step">2.</span>
    <?php echo _("Groups") ?>
   </h2>
   <p class="perms-scope-help"><?php echo _("Per-group overrides. Grants from every group the user belongs to collect, then denies from those same groups subtract.") ?></p>

   <?php if (!empty($this->group_grants) || !empty($this->group_denies)): ?>
    <?php
    $groupNames = array_unique(array_merge(
        array_keys($this->group_grants),
        array_keys($this->group_denies)
    ));
    sort($groupNames);
    ?>
    <?php foreach ($groupNames as $gid): ?>
     <?php echo $this->renderPartial('tri_row', ['locals' => [
         'scope' => 'g',
         'name' => $gid,
         'label' => $this->group_list[$gid] ?? $gid,
         'grant' => $this->group_grants[$gid] ?? 0,
         'deny' => $this->group_denies[$gid] ?? 0,
         'cols' => $this->cols,
         'type' => $this->type,
     ]]) ?>
    <?php endforeach; ?>
   <?php else: ?>
    <p class="perms-empty"><?php echo _("No group overrides yet.") ?></p>
   <?php endif; ?>

   <fieldset class="perms-new-row">
    <legend><?php echo _("Add a group") ?></legend>
    <?php if (!empty($this->new_groups)): ?>
     <label>
      <?php echo _("Group") ?>
      <select name="g_new[name]">
       <option value=""><?php echo _("-- select --") ?></option>
       <?php foreach ($this->new_groups as $gid => $label): ?>
        <option value="<?php echo $this->h((string) $gid) ?>"><?php echo $this->h($label) ?></option>
       <?php endforeach; ?>
      </select>
     </label>
    <?php else: ?>
     <label>
      <?php echo _("Group") ?>
      <input type="text" name="g_new[name]" />
     </label>
    <?php endif; ?>
    <?php echo $this->renderPartial('tri_scope', ['locals' => [
        'scope' => 'g_new[value]',
        'label_grant' => 0,
        'label_deny' => 0,
        'cols' => $this->cols,
        'type' => $this->type,
    ]]) ?>
   </fieldset>
  </section>

  <?php // ------- Creator: user IS the object's creator ------- ?>
  <section class="perms-scope perms-scope-cascade" data-scope="creator" data-cascade-step="3">
   <h2 class="perms-scope-header">
    <span class="perms-scope-step">3.</span>
    <?php echo _("Creator") ?>
   </h2>
   <p class="perms-scope-help"><?php echo _("Applies when the acting user created the object. Beats grants at the default and group levels.") ?></p>
   <?php echo $this->renderPartial('tri_scope', ['locals' => [
       'scope' => 'creator',
       'label_grant' => $this->creator_grant,
       'label_deny' => $this->creator_deny,
       'cols' => $this->cols,
       'type' => $this->type,
   ]]) ?>
  </section>

  <?php // ------- Individual Users: final override ------- ?>
  <section class="perms-scope perms-scope-cascade perms-scope-list" data-scope="u" data-cascade-step="4">
   <h2 class="perms-scope-header">
    <span class="perms-scope-step">4.</span>
    <?php echo _("Individual Users") ?>
   </h2>
   <p class="perms-scope-help"><?php echo _("The final say. A per-user grant can restore a bit denied at group or default level. A per-user deny beats every broader grant.") ?></p>

   <?php if (!empty($this->user_grants) || !empty($this->user_denies)): ?>
    <?php
    // Collect every name that has any grant or deny bits so we render
    // one row per user, even if their grants and denies live in
    // different hashes.
    $userNames = array_unique(array_merge(
        array_keys($this->user_grants),
        array_keys($this->user_denies)
    ));
    sort($userNames);
    ?>
    <?php foreach ($userNames as $uid): ?>
     <?php echo $this->renderPartial('tri_row', ['locals' => [
         'scope' => 'u',
         'name' => $uid,
         'label' => $this->user_list[$uid] ?? $uid,
         'grant' => $this->user_grants[$uid] ?? 0,
         'deny' => $this->user_denies[$uid] ?? 0,
         'cols' => $this->cols,
         'type' => $this->type,
     ]]) ?>
    <?php endforeach; ?>
   <?php else: ?>
    <p class="perms-empty"><?php echo _("No individual user overrides yet.") ?></p>
   <?php endif; ?>

   <fieldset class="perms-new-row">
    <legend><?php echo _("Add a user") ?></legend>
    <?php if (!empty($this->new_users)): ?>
     <label>
      <?php echo _("User") ?>
      <select name="u_new[name]">
       <option value=""><?php echo _("-- select --") ?></option>
       <?php foreach ($this->new_users as $uid => $label): ?>
        <option value="<?php echo $this->h((string) $uid) ?>"><?php echo $this->h($label) ?></option>
       <?php endforeach; ?>
      </select>
     </label>
    <?php else: ?>
     <label>
      <?php echo _("User") ?>
      <input type="text" name="u_new[name]" />
     </label>
    <?php endif; ?>
    <?php echo $this->renderPartial('tri_scope', ['locals' => [
        'scope' => 'u_new[value]',
        'label_grant' => 0,
        'label_deny' => 0,
        'cols' => $this->cols,
        'type' => $this->type,
    ]]) ?>
   </fieldset>
  </section>
 </div>

 <p class="perms-buttons horde-form-buttons">
  <input type="submit" class="horde-default" value="<?php echo $this->h(_("Save Permissions")) ?>" />
 </p>
</form>
</div>
