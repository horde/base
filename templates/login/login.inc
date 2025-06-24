<div class="modal-form">

<?php if (!is_null($auth_label)): ?>
 <div class="label login-app"><?php echo $auth_label ?></div>
<?php endif; ?>

<?php $notification->notify(array('listeners' => 'status')) ?>

<form name="horde_login" id="horde_login" method="post" action="<?php echo $loginurl ?>">
 <input type="hidden" name="app" id="app" value="<?php echo htmlspecialchars((string)$vars->app) ?>" />
 <input type="hidden" name="login_post" id="login_post" value="0" />
 <input type="hidden" name="url" value="<?php echo htmlspecialchars((string)$vars->url) ?>" />
 <input type="hidden" name="anchor_string" id="anchor_string" value="<?php echo htmlspecialchars((string)$vars->anchor_string) ?>" />

<?php
  // TODO: Implement supporting functions elsewhere
  $addLine = function($value) use (&$lines, &$indent) {
    $lines[] = str_repeat(' ', $indent) . $value;
  };

  $addParm = function($parm, $value = null) use (&$parms) {
    if (!is_null($value)) {
      $parm .= '="' . htmlspecialchars($value) . '"';
    }
    $parms[] = $parm;
  };
  
  $lines = [];
  $indent = 0;

  foreach ($loginparams as $key => $val) {
    $type = $val['type'] ?? '';
    $label = $val['label'] ?? '';
    $value = $val['value'] ?? '';
    $extra = $val['extra'] ?? '';

    switch ($type) {
      case 'text':
        if (empty($extra)) {
          // default
          $extra = [
            'autocapitalize' => 'off',
            'autocorrect' => 'off',
          ];
        }
        break;
      case 'password':
        $value = '';
        break;
    }
 
    $div = $val['div'] ?? '';
    if (!empty($div)) {
      $parms = [ 'div' ];
      if (!is_array($div)) {
        $div = [ $div ];
      }
      foreach ($div as $k => $v) {
        $addParm($k, $v);
      }
      $div = $parms;
    }
 
    $parms = [];

    if ($type == 'select') {
      $addParm($type);
    } else {
      $addParm('input');
      $addParm('type' , $type);
    }

    if (!empty($extra)) {
      if (!is_array($extra)) {
        $extra = [ $extra ];
      }
      foreach ($extra as $k => $v) {
        $addParm($k, $v);
      }
    }
 
    $addParm('id', $key);
    $addParm('name', $key);

    if (is_string($value)) {
      $addParm('value', htmlspecialchars($value));;
    }
 
    if (!empty($div)) {
      $addLine('<' . implode(' ', $div) . '>');
      ++$indent;
    }

    if ($label != '') {
      $addLine('<div>' . Horde::label($key, $label) . '</div>');
    }

    $addLine('<div>');
    ++$indent;
 
    if ($type == 'select') {
      $addLine('<' . implode(' ', $parms) . '>');
      ++$indent;
      foreach ($value as $k2 => $v2) {
        $parms = [];
        $addParm('option');;
        if (is_null($v2)) {
          $addParm('value', '');
          $addParm('disabled', 'disabled');
          $value = '- - - - - - - - - -';
        } else {
          $addParm('value', $k2);
          if (!empty($v2['selected'])) {
            $addParm('selected', 'selected');
          }
          if (!empty($v2['hidden'])) {
            $addParm('style', 'display:none');
          }
          $value = $v2['name'];
        }
        $addLine('<' . implode(' ', $parms) . '>' . htmlspecialchars($value) . '</option>');
      }
      --$indent;
      $addLine('</select>');
    } else {
      if ($type != 'hidden') {
        $addParm('style' , 'direction:ltr');
      }
      $addLine('<' . implode(' ', $parms) . ' />');
    }

    --$indent;
    $addLine('</div>');

    if ($key == 'horde_pass') {
      $addLine('<div id="horde-login-pass-capslock" style="display:none">' . _("Warning: Your Caps Lock key is on!") . '</div>');
    }

    if (!empty($div)) {
      --$indent;
      $addLine('</div>');
    }
  }
  print implode("\n", $lines);
?>

<?php if (!$is_auth && !$prefs->isLocked('language')): ?>
<div><?php echo Horde::label('new_lang', _("Language")) ?></div>
<div><select id="new_lang" name="new_lang">
<?php foreach ($langs as $val): ?>
        <option value="<?php echo $val['val'] ?>"<?php echo ($val['sel'] ? ' selected="selected"' : '') ?>><?php echo $val['name'] ?></option>
<?php endforeach; ?>
</select></div>
<?php endif; ?>

<div>
 <input id="login-button" name="login_button" class="horde-default submit-button" value="<?php echo _("Log in") ?>" type="submit" />
</div>

<?php
$add_user = (!empty($conf['signup']['allow']) && $auth->hasCapability('add'));
$reset_passwd = !empty($conf['auth']['resetpassword']) && $auth->hasCapability('resetpassword');
?>

<?php if ($add_user || $reset_passwd): ?>
<div class="extras">
<?php if ($add_user): ?>
 <?php echo Horde::link(Horde::url('signup.php', false, array('app' => 'horde'))->add('url', $vars->url), _("Don't have an account? Sign up."), 'light') . _("Don't have an account? Sign up.") ?></a>
<?php endif; ?>

<?php if ($reset_passwd): ?>
<?php if ($add_user): ?>
 <br />
<?php endif; ?>
 <?php echo Horde::link(Horde::url('services/resetpassword.php', false, array('app' => 'horde'))->add('url', $vars->url), _("Forgot your password?"), 'light') . _("Forgot your password?") ?></a>
<?php endif; ?>
</div>
<?php endif; ?>

</form>
</div>

<?php
try {
    echo $registry->loadConfigFile('motd.php', 'motd', 'horde')->config['motd'];
} catch (Horde_Exception $e) {}
