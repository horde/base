<?php
/**
 * Horde test script.
 *
 * Parameters:
 * -----------
 *   - app: (string) The app to test.
 *          DEFAULT: horde
 *   - mode: (string) TODO
 *   - url: (string) TODO
 *
 * Copyright 1999-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Brent J. Nordquist <bjn@horde.org>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Http\Uri;
use Horde\Util\Util;

/* Function to output fatal error message. */
function _hordeTestError($msg)
{
    exit('<html><head><title>ERROR</title></head><body><h3 style="color:red">' . htmlspecialchars($msg) . '</h3></body></html>');
}

/* If we can't find the Autoloader, then the framework is not setup. A user
 * must at least correctly install the framework. */
set_include_path(__DIR__ . '/lib' . PATH_SEPARATOR . get_include_path());
if (file_exists(__DIR__ . '/config/horde.local.php')) {
    include __DIR__ . '/config/horde.local.php';
}
if (!@include_once 'Horde/Autoloader.php') {
    _hordeTestError(sprintf('Could not find Horde\'s framework libraries in the following path(s): %s. Please read horde/doc/INSTALL for information on how to install these libraries.', get_include_path()));
}

/* Similarly, registry.php needs to exist. */
if (!file_exists(__DIR__ . '/config/registry.php')) {
    _hordeTestError('Could not find horde/config/registry.php. Please make sure this file exists. Read horde/doc/INSTALL for further information.');
}

require_once __DIR__ . '/lib/Application.php';
try {
    Horde_Registry::appInit('horde', [
        'authentication' => 'none',
        'test' => true,
    ]);
    $init_exception = null;
} catch (Exception $e) {
    $expected_templates = __DIR__ . '/templates';
    if (defined('HORDE_TEMPLATES')) {
        if (HORDE_TEMPLATES !== $expected_templates) {
            error_log('HORDE_TEMPLATES mismatch: expected ' . $expected_templates . ', got ' . HORDE_TEMPLATES);
        }
    } else {
        define('HORDE_TEMPLATES', $expected_templates);
    }
    $init_exception = $e;
}

if (!empty($conf['testdisable'])) {
    _hordeTestError('Horde test scripts have been disabled in the local configuration. To enable, change the \'testdisable\' setting in horde/config/conf.php to false.');
}

/* We should have loaded the String class, from the Horde_Util package. If it
 * isn't defined, then we're not finding some critical libraries. */
if (!class_exists('Horde_String')) {
    _hordeTestError('Required Horde libraries were not found. If PHP\'s error_reporting setting is high enough and display_errors is on, there should be error messages printed above that may help you in debugging the problem. If you are simply missing these files, then you need to install the framework module.');
}

/* Initialize the Horde_Test:: class. */
if (!class_exists('Horde_Test')) {
    /* Try and provide enough information to debug the missing file. */
    _hordeTestError('Unable to find the Horde_Test library. Your Horde installation may be missing critical files, or PHP may not have sufficient permissions to include files. There may be error messages printed above this message that will help you in debugging the problem.');
}

/* Load the application. */
$app = Util::getFormData('app', 'horde');
$test_type = Util::getFormData('type', '');
$app_name = $registry->get('name', $app);
$app_version = $registry->getVersion($app);

/* If we've gotten this far, we should have found enough of Horde to run
 * tests. Create the testing object. */
if ($app != 'horde') {
    try {
        $registry->pushApp($app, ['check_perms' => false]);
    } catch (Exception $e) {
        _hordeTestError($e->getMessage());
    }
}
$classname = ucfirst($app) . '_Test';
if (!class_exists($classname)) {
    _hordeTestError('No tests found for ' . ucfirst($app) . ' [' . $app_name . '].');
}
$test_ob = new $classname();

/* Register a session. */
if ($session && $session->sessionHandler && !$session->exists('horde', 'test_count')) {
    $session->set('horde', 'test_count', 0);
}

/* Template location. */
$test_templates = HORDE_TEMPLATES . '/test';

/* Get webroot for URL building. */
$webroot = $registry->get('webroot', 'horde');

/* Self URL. */
$url = new Uri($webroot . '/test.php');
$self_url = clone $url;

/* Handle special modes - BC compatibility, redirect to type parameter. */
$mode = Util::getGet('mode');
if ($mode) {
    // Backward compatibility: redirect old mode URLs to new type URLs
    if ($mode === 'extensions' || $mode === 'phpinfo') {
        $redirect_url = (new Uri($webroot . '/test.php'))->withQuery(http_build_query([
            'app' => 'horde',
            'type' => $mode,
            'ext' => Util::getGet('ext'),  // Preserve ext parameter for extensions
        ]));
        header('Location: ' . (string) $redirect_url);
        exit;
    }

    // Keep unregister as a mode since it's a special action, not a test page
    if ($mode === 'unregister') {
        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">';
        $session->remove('horde', 'test_count');
        ?>
<html>
 <body>
 The test session has been unregistered.<br />
 <a href="<?php echo $self_url ?>">Go back</a> to the test.php page.<br />
 </body>
</html>
<?php
        exit;
    }
}

/* Get the status output now. */
$pear_output = $test_ob->pearModuleCheck();
Horde::startBuffer();
require $test_templates . '/header.inc';
require $test_templates . '/version.inc';

/* Do application specific tests now. */
if (!empty($test_type)) {
    // Running a specific test type - render sub-test content first
    if (method_exists($test_ob, 'appTestType')) {
        $type_output = $test_ob->appTestType($test_type);
        if (!empty($type_output)) {
            echo $type_output;
        } else {
            // Unknown type
            echo '<h1>Test Type Not Found</h1>';
            echo '<div class="error-box" style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">';
            echo '<strong style="color:#e74c3c">Error:</strong> Test type <code>' . htmlspecialchars($test_type) . '</code> is not supported by this application.';
            echo '<br /><br /><a href="' . htmlspecialchars((string) $self_url) . '">Return to main test page</a>';
            echo '</div>';
        }
    } else {
        // Test class doesn't support sub-types
        echo '<h1>Test Types Not Supported</h1>';
        echo '<div class="error-box" style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">';
        echo '<strong style="color:#e74c3c">Error:</strong> This application does not support test types.';
        echo '<br /><br /><a href="' . htmlspecialchars((string) $self_url) . '">Return to main test page</a>';
        echo '</div>';
    }

    // Show Horde Applications list at bottom if viewing horde app sub-test
    if ($app == 'horde') {
        ?>
<h1>Horde Applications</h1>
<ul>
<?php
        /* Get Horde module version information. */
        if (!$init_exception) {
            try {
                // Show Base (horde) first, then other apps alphabetically
                $app_list = array_diff($registry->listAllApps(), [$app]);
                sort($app_list);
                array_unshift($app_list, 'horde');

                foreach ($app_list as $val) {
                    echo '<li>' . ucfirst($val);
                    if ($name = $registry->get('name', $val)) {
                        echo ' [' . $name . ']';
                    }
                    echo ': ' . $registry->getVersion($val);

                    $test_file = $registry->get('fileroot', $val) . '/lib/Test.php';
                    if (file_exists($test_file)) {
                        // Check if app has Test class with sub-types
                        $test_classname = ucfirst($val) . '_Test';
                        $has_subtypes = false;
                        $subtypes = [];

                        if (class_exists($test_classname)) {
                            $test_instance = new $test_classname();
                            if (method_exists($test_instance, 'appTestTypes')) {
                                $subtypes = $test_instance->appTestTypes();
                                $has_subtypes = !empty($subtypes);
                            }
                        }

                        // Main test link
                        $app_url = (clone $url)->withQuery(http_build_query(['app' => $val]));
                        echo ' (<a href="' . htmlspecialchars((string) $app_url) . '">run tests</a>';

                        // Sub-type links
                        if ($has_subtypes) {
                            $subtype_links = [];
                            foreach ($subtypes as $type_key => $type_name) {
                                $subtype_url = (clone $url)->withQuery(http_build_query(['app' => $val, 'type' => $type_key]));
                                $subtype_links[] = '<a href="' . htmlspecialchars((string) $subtype_url) . '">' . htmlspecialchars($type_name) . '</a>';
                            }
                            echo ' | ' . implode(' | ', $subtype_links);
                        }

                        echo ')</li>';
                    } else {
                        echo '</li>';
                    }

                    echo "\n";
                }
            } catch (Exception $e) {
                $init_exception = $e;
            }
        }

        if ($init_exception) {
            echo '<li style="color:red"><strong>Horde is not correctly configured so no application information can be displayed. Please follow the instructions in horde/doc/INSTALL and ensure horde/config/conf.php and horde/config/registry.php are correctly configured.</strong></li>'
                . '<li><strong>Error:</strong> ' . $e->getMessage() . '</li>';
        }
        ?>
</ul>
<?php
    }
} else {
    // Running default tests - show all sections in normal order

    if ($app == 'horde') {
        ?>
<h1>Horde Applications</h1>
<ul>
<?php
            /* Get Horde module version information. */
            if (!$init_exception) {
                try {
                    // Show Base (horde) first, then other apps alphabetically
                    $app_list = array_diff($registry->listAllApps(), [$app]);
                    sort($app_list);
                    array_unshift($app_list, 'horde');

                    foreach ($app_list as $val) {
                        echo '<li>' . ucfirst($val);
                        if ($name = $registry->get('name', $val)) {
                            echo ' [' . $name . ']';
                        }
                        echo ': ' . $registry->getVersion($val);

                        $test_file = $registry->get('fileroot', $val) . '/lib/Test.php';
                        if (file_exists($test_file)) {
                            // Check if app has Test class with sub-types
                            $test_classname = ucfirst($val) . '_Test';
                            $has_subtypes = false;
                            $subtypes = [];

                            if (class_exists($test_classname)) {
                                $test_instance = new $test_classname();
                                if (method_exists($test_instance, 'appTestTypes')) {
                                    $subtypes = $test_instance->appTestTypes();
                                    $has_subtypes = !empty($subtypes);
                                }
                            }

                            // Main test link
                            $app_url = (clone $url)->withQuery(http_build_query(['app' => $val]));
                            echo ' (<a href="' . htmlspecialchars((string) $app_url) . '">run tests</a>';

                            // Sub-type links
                            if ($has_subtypes) {
                                $subtype_links = [];
                                foreach ($subtypes as $type_key => $type_name) {
                                    $subtype_url = (clone $url)->withQuery(http_build_query(['app' => $val, 'type' => $type_key]));
                                    $subtype_links[] = '<a href="' . htmlspecialchars((string) $subtype_url) . '">' . htmlspecialchars($type_name) . '</a>';
                                }
                                echo ' | ' . implode(' | ', $subtype_links);
                            }

                            echo ')</li>';
                        } else {
                            echo '</li>';
                        }

                        echo "\n";
                    }
                } catch (Exception $e) {
                    $init_exception = $e;
                }
            }

        if ($init_exception) {
            echo '<li style="color:red"><strong>Horde is not correctly configured so no application information can be displayed. Please follow the instructions in horde/doc/INSTALL and ensure horde/config/conf.php and horde/config/registry.php are correctly configured.</strong></li>'
                . '<li><strong>Error:</strong> ' . $e->getMessage() . '</li>';
        }
        ?>
</ul>
<?php
    } elseif ($output = $test_ob->requiredAppCheck()) {
        ?>
<h1>Other Horde Applications</h1>
<ul>
 <?php echo $output ?>
</ul>
<?php
    }

    /* Display PHP Version information. */
    $php_info = $test_ob->getPhpVersionInformation();
    require $test_templates . '/php_version.inc';

    /* Autoloader and Sub-system checks */
    $autoloader_output = $test_ob->_autoloaderCheck();
    if ($autoloader_output) {
        ?>
<h1>Autoloader Paths</h1>
<ul>
    <?php echo $autoloader_output ?>
</ul>
<?php
    }

    $subsystem_output = $test_ob->_subSystemCheck();
    if ($subsystem_output) {
        ?>
<h1>Sub-System Tests</h1>
<ul>
    <?php echo $subsystem_output ?>
</ul>
<?php
    }

    if ($module_output = $test_ob->phpModuleCheck()) {
        ?>
<h1>PHP Module Capabilities</h1>
<ul>
 <?php echo $module_output ?>
</ul>
<?php
    }

    if ($setting_output = $test_ob->phpSettingCheck()) {
        ?>
<h1>Miscellaneous PHP Settings</h1>
<ul>
 <?php echo $setting_output ?>
</ul>
<?php
    }

    if ($config_output = $test_ob->requiredFileCheck()) {
        ?>
<h1>Required Configuration Files</h1>
<ul>
    <?php echo $config_output ?>
</ul>
<?php
    }
    ?>

<h1>PHP Sessions</h1>
<ul>
<?php if (!$init_exception && $session->sessionHandler): ?>
 <li>Session counter: <?php $tc = $session->get('horde', 'test_count');
    echo ++$tc;
    $session->set('horde', 'test_count', $tc); ?> [refresh the page to increment the counter]</li>
 <li>To unregister the session: <a href="<?php $unregister_url = (clone $self_url)->withQuery(http_build_query(['mode' => 'unregister'])); echo htmlspecialchars((string) $unregister_url); ?>">click here</a></li>
<?php elseif (!$init_exception): ?>
 <li style="color:orange"><strong>Session handler not available - session test disabled</strong></li>
<?php else: ?>
 <li style="color:red"><strong>The PHP session test is disabled until Horde is correctly configured.</strong></li>
<?php endif; ?>
</ul>

<h1>Troubleshooting Tools</h1>
<ul>
 <li><a href="<?php $static_test_url = new Uri($webroot . '/test-static.php'); echo htmlspecialchars((string) $static_test_url); ?>">Static Assets Troubleshooting</a> - Diagnose JS and CSS caching/misconfiguration issues</li>
</ul>

<h1>PHP Libraries</h1>
<ul>
    <?php echo $pear_output ?>
</ul>

<?php

    /* Do application specific tests now. */
    echo $test_ob->appTests();
}

require $test_templates . '/footer.inc';
echo Horde::endBuffer();
