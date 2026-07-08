<?php
/**
 * Partial: single numeric input for a non-matrix, non-boolean scope.
 *
 * Used for permission types like 'int' (nag's max_tasks, imp's mailbox
 * quota, etc.). Numeric permissions do not participate in the
 * grant / deny cascade because there is no principled way to "deny 47".
 * The data layer throws HordeLogicException on numeric denies. The UI
 * mirrors that by emitting only a grant input per scope.
 *
 * Empty input means "no rule at this scope" and Horde_Perms will
 * unset the value at save time.
 *
 * Expected locals:
 *   $scope   Form field prefix ('default', 'guest', 'creator',
 *            'u_new[value]', or 'u[alice]' for a per-name row).
 *   $number  Current value (int or null).
 *
 *   Note: this partial deliberately does NOT accept a local named
 *   $value. Horde_View::_run() uses $value as its foreach iteration
 *   variable when unpacking the locals array, so any local named
 *   'value' gets clobbered by the next iteration in that loop. Every
 *   Horde_View partial in this codebase must avoid the name.
 */
$displayValue = ($number === null || $number === '') ? '' : (string) $number;
$fieldName = $scope;
$idBase = 'perms-' . preg_replace('/[^a-z0-9]+/i', '-', $scope);
?>
<div class="perms-numeric-cell">
 <label for="<?php echo $this->h($idBase) ?>" class="perms-numeric-label">
  <?php echo _("Value") ?>
 </label>
 <input type="number"
        id="<?php echo $this->h($idBase) ?>"
        name="<?php echo $this->h($fieldName) ?>"
        value="<?php echo $this->h($displayValue) ?>"
        class="perms-numeric-input" />
 <span class="perms-numeric-hint"><?php echo _("Leave empty for no rule at this scope.") ?></span>
</div>
