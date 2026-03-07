<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
<html>
 <head>
  <title><?php echo $app_name ?>: System Capabilities Test</title>
  <style type="text/css"><?php echo file_get_contents(__DIR__ . '/test.css') ?></style>
  <?php
  // Attempt to load modern theme stylesheet from HORDE_BASE/themes/default/tests.css
  if (defined('HORDE_BASE')) {
      $theme_css_path = HORDE_BASE . '/themes/default/tests.css';
      if (file_exists($theme_css_path)) {
          echo '<style type="text/css">' . file_get_contents($theme_css_path) . '</style>';
      }
  }
  ?>
 </head>
 <body>
