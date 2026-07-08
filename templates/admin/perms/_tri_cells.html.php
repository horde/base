<?php
/**
 * Partial: tri-state cells for a single (scope, name?) tuple.
 *
 * Renders one three-radio group per permission column. Matrix types
 * render one group per bit (SHOW / READ / EDIT / DELETE). Boolean
 * types render one group total, storing the verdict under the SHOW
 * key so the server-side foldTriState() handles both shapes uniformly.
 *
 * Expected locals:
 *   $scope  Form field prefix. Examples:
 *           'default'         (scope-level radio, name="default[SHOW]")
 *           'u[alice]'        (per-name, name="u[alice][SHOW]")
 *           'u_new[value]'    (add-new row, name="u_new[value][SHOW]")
 *   $grant  int bitmask (matrix) or bool (boolean). Current grant.
 *   $deny   int bitmask (matrix) or bool (boolean). Current deny.
 *   $cols   Horde_Perms::getPermsArray() output (bit => label).
 *   $type   'matrix' | 'boolean' | other.
 *
 * A cell is:
 *   grant    when the bit is set in $grant
 *   deny     when the bit is set in $deny
 *   neutral  otherwise
 * The three-way check is exclusive by construction. The Perms 3.1
 * data layer never stores the same bit as both grant and deny for
 * the same (scope, name) tuple.
 */

$isBoolean = ($type === 'boolean');
$bits = $isBoolean
    ? [Horde_Perms::SHOW => _("Enabled")]
    : $cols;
?>
<div class="perms-tri-cells" role="group">
 <?php foreach ($bits as $bit => $bitLabel):
     if ($isBoolean) {
         $isGrant = (bool) $grant;
         $isDeny = (bool) $deny;
     } else {
         $isGrant = (bool) ($grant & $bit);
         $isDeny = (bool) ($deny & $bit);
     }
     $verdict = $isGrant ? 'grant' : ($isDeny ? 'deny' : 'neutral');
     $fieldName = $scope . '[' . $bit . ']';
     $idBase = 'perms-' . preg_replace('/[^a-z0-9]+/i', '-', $scope . '-' . $bit);
 ?>
 <fieldset class="perms-tri" role="radiogroup" aria-label="<?php echo $this->h($bitLabel) ?>">
  <legend class="perms-tri-legend"><?php echo $this->h($bitLabel) ?></legend>

  <input type="radio" id="<?php echo $this->h($idBase . '-g') ?>"
         name="<?php echo $this->h($fieldName) ?>" value="grant"
         <?php echo $verdict === 'grant' ? 'checked="checked"' : '' ?> />
  <label for="<?php echo $this->h($idBase . '-g') ?>" class="perms-tri-label perms-tri-grant"
         title="<?php echo $this->h(_("Grant this permission at this scope")) ?>">
   <span aria-hidden="true">✓</span>
   <span class="perms-tri-text"><?php echo _("Grant") ?></span>
  </label>

  <input type="radio" id="<?php echo $this->h($idBase . '-n') ?>"
         name="<?php echo $this->h($fieldName) ?>" value="neutral"
         <?php echo $verdict === 'neutral' ? 'checked="checked"' : '' ?> />
  <label for="<?php echo $this->h($idBase . '-n') ?>" class="perms-tri-label perms-tri-neutral"
         title="<?php echo $this->h(_("No rule at this scope")) ?>">
   <span aria-hidden="true">−</span>
   <span class="perms-tri-text"><?php echo _("No rule") ?></span>
  </label>

  <input type="radio" id="<?php echo $this->h($idBase . '-d') ?>"
         name="<?php echo $this->h($fieldName) ?>" value="deny"
         <?php echo $verdict === 'deny' ? 'checked="checked"' : '' ?> />
  <label for="<?php echo $this->h($idBase . '-d') ?>" class="perms-tri-label perms-tri-deny"
         title="<?php echo $this->h(_("Deny this permission at this scope")) ?>">
   <span aria-hidden="true">✕</span>
   <span class="perms-tri-text"><?php echo _("Deny") ?></span>
  </label>
 </fieldset>
 <?php endforeach; ?>
</div>
