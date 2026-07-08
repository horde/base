<?php
/**
 * Partial: cell block for a single scope (default, creator).
 *
 * Dispatches between tri-state cells (matrix / boolean, which support
 * deny) and a numeric input (int / other non-matrix, non-boolean types,
 * which do not). Kept as its own partial so scope-level markup can
 * grow (bulk-action buttons, per-scope explainer, etc.) without
 * changing the atomic cell partials.
 *
 * Expected locals:
 *   $scope        Scope name ('default' | 'creator').
 *   $label_grant  Current grant value (int mask for matrix, bool for
 *                 boolean, scalar for numeric, or null).
 *   $label_deny   Current deny value. Ignored for numeric types
 *                 because denies are not defined for them.
 *   $cols         Horde_Perms::getPermsArray() output.
 *   $type         'matrix' | 'boolean' | other.
 */
$isTri = ($type === 'matrix' || $type === 'boolean');
?>
<div class="perms-scope-body">
 <?php if ($isTri): ?>
  <?php echo $this->renderPartial('tri_cells', ['locals' => [
      'scope' => $scope,
      'grant' => $label_grant ?? 0,
      'deny' => $label_deny ?? 0,
      'cols' => $cols,
      'type' => $type,
  ]]) ?>
 <?php else: ?>
  <?php echo $this->renderPartial('numeric_scope', ['locals' => [
      'scope' => $scope,
      'number' => $label_grant,
  ]]) ?>
 <?php endif; ?>
</div>
