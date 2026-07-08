<?php
/**
 * Partial: row for one user or group.
 *
 * Dispatches between tri-state cells (matrix / boolean, which support
 * deny) and a numeric input (int / other non-matrix, non-boolean).
 * The row is the responsive unit. Desktop CSS lays it out as a
 * horizontal strip. Mobile CSS stacks the label above the cells.
 *
 * Expected locals:
 *   $scope  'u' or 'g'. The outer form key.
 *   $name   User id or group id (used in the field name path).
 *   $label  Human-readable label for the identity.
 *   $grant  Current grant value (int mask, bool, or scalar).
 *   $deny   Current deny value. Ignored for numeric types.
 *   $cols   Horde_Perms::getPermsArray() output.
 *   $type   'matrix' | 'boolean' | other.
 */

$prefix = $scope . '[' . $name . ']';
$isTri = ($type === 'matrix' || $type === 'boolean');
?>
<div class="perms-row" data-name="<?php echo $this->h((string) $name) ?>">
 <div class="perms-row-label">
  <?php echo $this->h((string) $label) ?>
 </div>
 <div class="perms-row-cells">
  <?php if ($isTri): ?>
   <?php echo $this->renderPartial('tri_cells', ['locals' => [
       'scope' => $prefix,
       'grant' => $grant,
       'deny' => $deny,
       'cols' => $cols,
       'type' => $type,
   ]]) ?>
  <?php else: ?>
   <?php echo $this->renderPartial('numeric_scope', ['locals' => [
       'scope' => $prefix,
       'number' => $grant,
   ]]) ?>
  <?php endif; ?>
 </div>
</div>
