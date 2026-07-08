<?php
/**
 * Partial: grant-only cell block for a single scope.
 *
 * Used for the guest scope, which is disjunct from the deny cascade.
 * Renders one checkbox per permission bit (matrix / boolean) or a
 * single numeric input (int / other non-matrix, non-boolean types).
 * Submitted values decode via readScopeGrantOnly() in the Ui class.
 *
 * Expected locals:
 *   $scope  Scope name (currently only 'guest').
 *   $grant  Current grant value (int mask, bool, or scalar). May be
 *           null when no rule is stored.
 *   $cols   Horde_Perms::getPermsArray() output.
 *   $type   'matrix' | 'boolean' | other.
 */

$isMatrix = ($type === 'matrix');
$isBoolean = ($type === 'boolean');

if (!$isMatrix && !$isBoolean) {
    echo $this->renderPartial('numeric_scope', ['locals' => [
        'scope' => $scope,
        'number' => $grant,
    ]]);
    return;
}

$bits = $isBoolean
    ? [Horde_Perms::SHOW => _("Enabled")]
    : $cols;
$mask = $grant ?? 0;
?>
<div class="perms-scope-body perms-grant-only">
 <?php foreach ($bits as $bit => $bitLabel):
     $isSet = $isBoolean ? (bool) $mask : (bool) ($mask & $bit);
     $fieldName = $scope . '[' . $bit . ']';
     $idBase = 'perms-' . preg_replace('/[^a-z0-9]+/i', '-', $scope . '-' . $bit);
 ?>
 <?php // The server-side foldTriState() reads 'grant' vs anything-else.
       // A missing key is treated as neutral. So we can render this as
       // a checkbox whose checked value is 'grant', without needing the
       // full three-state radio group. ?>
 <label class="perms-grant-cell" for="<?php echo $this->h($idBase) ?>">
  <input type="checkbox" id="<?php echo $this->h($idBase) ?>"
         name="<?php echo $this->h($fieldName) ?>" value="grant"
         <?php echo $isSet ? 'checked="checked"' : '' ?> />
  <?php echo $this->h($bitLabel) ?>
 </label>
 <?php endforeach; ?>
</div>
