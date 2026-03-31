<?php

use Horde\Http\Uri;

/**
 * Protocols troubleshooting helper for Horde test page.
 *
 * Tests various protocol endpoints (ActiveSync, CalDAV, CardDAV, etc.)
 * to verify configuration and reachability.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */
class Horde_Test_Protocols
{
    /**
     * Generate HTML output for protocols testing page.
     *
     * @param Horde_Registry $registry  The registry instance
     *
     * @return string  HTML output
     */
    public static function render($registry)
    {
        $webroot = $registry->get('webroot', 'horde');

        // Get the actual config file path - use full filesystem path
        $config_file = defined('HORDE_CONFIG_BASE')
            ? HORDE_CONFIG_BASE . '/horde/conf.php'
            : 'horde/config/conf.php';

        // Determine ActiveSync endpoint URL
        // Horde delivers ActiveSync through the standard RPC endpoint
        $activesync_url = null;
        $activesync_status = 'Not configured';
        $activesync_status_color = 'red';
        $activesync_details = '';

        try {
            // Check if ActiveSync is explicitly enabled in configuration
            $conf = $GLOBALS['conf'] ?? null;
            if (isset($conf['activesync']['enabled']) && $conf['activesync']['enabled']) {
                // Check if the ActiveSync library is actually installed
                if (!class_exists('Horde_ActiveSync')) {
                    $activesync_status = 'Enabled but library missing';
                    $activesync_status_color = 'red';
                    $activesync_details = 'ActiveSync is enabled in <code>' . htmlspecialchars($config_file) . '</code> but the <code>Horde_ActiveSync</code> library is not installed.<br />'
                        . '<strong>Fix:</strong> Install the ActiveSync component: <code>composer require horde/activesync</code> or download from <a href="https://github.com/horde/ActiveSync" target="_blank">github.com/horde/ActiveSync</a>';
                } else {
                    $activesync_status = 'Enabled in configuration';
                    $activesync_status_color = 'green';
                    $activesync_url = $webroot . '/rpc.php';
                    $activesync_details = 'Set in <code>' . htmlspecialchars($config_file) . '</code>: <code>$conf[\'activesync\'][\'enabled\'] = true;</code>';
                }
            } elseif (isset($conf['activesync']['enabled']) && !$conf['activesync']['enabled']) {
                $activesync_status = 'Explicitly disabled in configuration';
                $activesync_status_color = 'red';
                $activesync_details = 'Set in <code>' . htmlspecialchars($config_file) . '</code>: <code>$conf[\'activesync\'][\'enabled\'] = false;</code><br />'
                    . 'To enable: Use <a href="' . htmlspecialchars($webroot . '/admin/config/config.php?app=horde') . '" target="_blank">Configuration UI</a> (ActiveSync tab) or edit <code>' . htmlspecialchars($config_file) . '</code>';
            }
        } catch (Exception $e) {
            // Configuration not available
        }

        // If not explicitly configured, check if the registry has activesync app
        if (!$activesync_url) {
            try {
                if ($registry->hasMethod('activesync')) {
                    // Check if the ActiveSync library is actually installed
                    if (!class_exists('Horde_ActiveSync')) {
                        $activesync_status = 'Detected but library missing';
                        $activesync_status_color = 'red';
                        $activesync_details = 'ActiveSync app is registered but the <code>Horde_ActiveSync</code> library is not installed.<br />'
                            . '<strong>Fix:</strong> Install the ActiveSync component: <code>composer require horde/activesync</code> or download from <a href="https://github.com/horde/ActiveSync" target="_blank">github.com/horde/ActiveSync</a>';
                    } else {
                        $activesync_url = $webroot . '/rpc.php';
                        if ($activesync_status === 'Not configured') {
                            $activesync_status = 'Detected via registry (default endpoint)';
                            $activesync_status_color = 'orange';
                            $activesync_details = 'ActiveSync app is installed. Using default endpoint. Configure in <a href="' . htmlspecialchars($webroot . '/admin/config/config.php?app=horde') . '" target="_blank">Configuration UI</a> (ActiveSync tab).';
                        }
                    }
                }
            } catch (Exception $e) {
                // Registry method check failed
            }
        }

        // If still not detected
        if (!$activesync_url && $activesync_status === 'Not configured') {
            $activesync_details = 'ActiveSync is not configured or installed. To enable: Install ActiveSync app and configure in <a href="' . htmlspecialchars($webroot . '/admin/config/config.php?app=horde') . '" target="_blank">Configuration UI</a>.';
        }

        // Encode for JavaScript
        $activesync_url_json = json_encode($activesync_url);

        ob_start();
        ?>
<h1>ActiveSync Protocol</h1>
<div style="background: white; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px; margin: 0 0 20px 0;">
    <h2 style="color: #34495e; font-size: 18px; font-weight: 600; margin: 20px 0 10px 0;">Endpoint Configuration</h2>
    <ul>
        <li><strong>Endpoint URL:</strong> <code><?php echo htmlspecialchars($activesync_url ?: 'Not detected') ?></code></li>
        <li><strong>Status:</strong> <span style="color:<?php echo $activesync_status_color ?>"><?php echo htmlspecialchars($activesync_status) ?></span></li>
        <?php if ($activesync_details): ?>
        <li><?php echo $activesync_details ?></li>
        <?php endif; ?>
    </ul>

    <div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">
        <strong>What this test does:</strong>
        <p style="margin: 10px 0 0 0;">
            ActiveSync clients perform an initial OPTIONS request to discover server capabilities.
            This test emulates that discovery step to verify:
        </p>
        <ul style="margin: 10px 0 0 20px; list-style: disc;">
            <li><strong>Endpoint is reachable:</strong> The ActiveSync URL responds to requests</li>
            <li><strong>OPTIONS method supported:</strong> Server accepts OPTIONS HTTP method</li>
            <li><strong>MS-ASProtocolVersions header:</strong> Server advertises supported ActiveSync protocol versions</li>
            <li><strong>MS-ASProtocolCommands header:</strong> Server advertises supported commands (Sync, FolderSync, etc.)</li>
            <li><strong>Not returning HTML:</strong> Validates proper ActiveSync routing (not a rogue controller)</li>
        </ul>
        <p style="margin: 10px 0 0 0;">
            <strong>Expected result:</strong> HTTP 200 with MS-ASProtocolVersions and MS-ASProtocolCommands headers.
        </p>
        <p style="margin: 10px 0 0 0;">
            <strong>Note:</strong> Test sends Content-Type header <code>application/vnd.ms-sync.wbxml</code> to trigger ActiveSync detection in rpc.php (see rpc.php line 36-37).
        </p>
    </div>

    <?php if ($activesync_url): ?>
    <h2 style="color: #34495e; font-size: 18px; font-weight: 600; margin: 20px 0 10px 0;">Endpoint Test</h2>
    <p><button id="test-activesync-btn" onclick="testActiveSync()">Test ActiveSync Endpoint</button></p>
    <div id="activesync-result" style="margin-top: 15px;"></div>
    <?php else: ?>
    <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">
        <strong style="color:#856404">⚠ WARNING</strong>
        ActiveSync endpoint URL could not be determined. Check your configuration.
    </div>
    <?php endif; ?>
</div>

<h1>JSON-RPC Protocol</h1>
<div style="background: white; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px; margin: 0 0 20px 0;">
    <h2 style="color: #34495e; font-size: 18px; font-weight: 600; margin: 20px 0 10px 0;">Endpoint Configuration</h2>
    <ul>
        <li><strong>Endpoint URL:</strong> <code><?php echo htmlspecialchars($webroot . '/rpc.php') ?></code></li>
        <li><strong>Detection:</strong> Content-Type: <code>application/json</code></li>
        <li><strong>Status:</strong> <span style="color:green">Available</span></li>
    </ul>

    <div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">
        <strong>What this test does:</strong>
        <p style="margin: 10px 0 0 0;">
            Tests the JSON-RPC endpoint by calling <code>horde.listApps</code> method.
            This verifies:
        </p>
        <ul style="margin: 10px 0 0 20px; list-style: disc;">
            <li><strong>Endpoint is reachable:</strong> The RPC URL responds to JSON-RPC requests</li>
            <li><strong>JSON-RPC protocol working:</strong> Server properly processes JSON-RPC 2.0 format</li>
            <li><strong>API methods available:</strong> Core Horde API methods are registered</li>
        </ul>
        <p style="margin: 10px 0 0 0;">
            <strong>Expected result:</strong> JSON-RPC response with list of available applications.
        </p>
        <p style="margin: 10px 0 0 0;">
            <strong>Note:</strong> Test sends Content-Type header <code>application/json</code> to trigger JSON-RPC detection in rpc.php (see rpc.php line 68-69).
        </p>
    </div>

    <h2 style="color: #34495e; font-size: 18px; font-weight: 600; margin: 20px 0 10px 0;">Endpoint Test</h2>
    <p><button id="test-jsonrpc-btn" onclick="testJsonRpc()">Test JSON-RPC Endpoint</button></p>
    <div id="jsonrpc-result" style="margin-top: 15px;"></div>
</div>

<h1>XML-RPC Protocol</h1>
<div style="background: white; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px; margin: 0 0 20px 0;">
    <h2 style="color: #34495e; font-size: 18px; font-weight: 600; margin: 20px 0 10px 0;">Endpoint Configuration</h2>
    <?php
        $xmlrpc_available = function_exists('xmlrpc_server_create');
        $xmlrpc_status_color = $xmlrpc_available ? 'green' : 'red';
        $xmlrpc_status = $xmlrpc_available ? 'Available' : 'PHP xmlrpc extension not installed';
    ?>
    <ul>
        <li><strong>Endpoint URL:</strong> <code><?php echo htmlspecialchars($webroot . '/rpc.php') ?></code></li>
        <li><strong>Detection:</strong> Content-Type: <code>text/xml</code></li>
        <li><strong>Status:</strong> <span style="color:<?php echo $xmlrpc_status_color ?>"><?php echo htmlspecialchars($xmlrpc_status) ?></span></li>
        <?php if (!$xmlrpc_available): ?>
        <li style="color:red">
            The <code>xmlrpc</code> PHP extension is required for XML-RPC support.<br />
            <strong>Fix:</strong> Install the extension: <code>apt-get install php-xmlrpc</code> or <code>pecl install xmlrpc</code>
        </li>
        <?php endif; ?>
    </ul>

    <div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">
        <strong>What this test does:</strong>
        <p style="margin: 10px 0 0 0;">
            Tests the XML-RPC endpoint by calling <code>horde.listApps</code> method.
            This verifies:
        </p>
        <ul style="margin: 10px 0 0 20px; list-style: disc;">
            <li><strong>Endpoint is reachable:</strong> The RPC URL responds to XML-RPC requests</li>
            <li><strong>XML-RPC protocol working:</strong> Server properly processes XML-RPC format</li>
            <li><strong>API methods available:</strong> Core Horde API methods are registered</li>
        </ul>
        <p style="margin: 10px 0 0 0;">
            <strong>Expected result:</strong> XML-RPC response with list of available applications.
        </p>
        <p style="margin: 10px 0 0 0;">
            <strong>Note:</strong> Test sends Content-Type header <code>text/xml</code> to trigger XML-RPC detection in rpc.php (see rpc.php line 62-67).
        </p>
    </div>

    <?php if ($xmlrpc_available): ?>
    <h2 style="color: #34495e; font-size: 18px; font-weight: 600; margin: 20px 0 10px 0;">Endpoint Test</h2>
    <p><button id="test-xmlrpc-btn" onclick="testXmlRpc()">Test XML-RPC Endpoint</button></p>
    <div id="xmlrpc-result" style="margin-top: 15px;"></div>
    <?php else: ?>
    <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">
        <strong style="color:#856404">⚠ WARNING</strong>
        XML-RPC endpoint test disabled - PHP extension not available.
    </div>
    <?php endif; ?>
</div>

<h1>SOAP Protocol</h1>
<div style="background: white; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px; margin: 0 0 20px 0;">
    <h2 style="color: #34495e; font-size: 18px; font-weight: 600; margin: 20px 0 10px 0;">Endpoint Configuration</h2>
    <?php
        // Check if SOAP extension is available
        $soap_available = class_exists('SoapServer');
        $soap_status_color = $soap_available ? 'green' : 'red';
        $soap_status = $soap_available ? 'Available' : 'PHP soap extension not installed';
    ?>
    <ul>
        <li><strong>Endpoint URL:</strong> <code><?php echo htmlspecialchars($webroot . '/rpc.php') ?></code></li>
        <li><strong>Detection:</strong> Content-Type: <code>text/xml</code> + SOAP envelope namespace</li>
        <li><strong>Status:</strong> <span style="color:<?php echo $soap_status_color ?>"><?php echo htmlspecialchars($soap_status) ?></span></li>
        <?php if (!$soap_available): ?>
        <li style="color:red">
            SOAP support requires the <code>soap</code> PHP extension.<br />
            <strong>Fix:</strong> Install the extension: <code>apt-get install php-soap</code>
        </li>
        <?php endif; ?>
    </ul>

    <div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">
        <strong>What this test does:</strong>
        <p style="margin: 10px 0 0 0;">
            Tests the SOAP endpoint by calling <code>horde.listApps</code> method via SOAP protocol.
            This verifies:
        </p>
        <ul style="margin: 10px 0 0 20px; list-style: disc;">
            <li><strong>Endpoint is reachable:</strong> The SOAP URL responds to requests</li>
            <li><strong>SOAP protocol working:</strong> Server properly processes SOAP envelope format</li>
            <li><strong>API methods available:</strong> Core Horde API methods are registered</li>
        </ul>
        <p style="margin: 10px 0 0 0;">
            <strong>Expected result:</strong> SOAP envelope response with list of available applications.
        </p>
        <p style="margin: 10px 0 0 0;">
            <strong>Note:</strong> Test sends Content-Type header <code>text/xml</code> with SOAP envelope to trigger SOAP detection in rpc.php (see rpc.php line 62-66).
        </p>
    </div>

    <?php if ($soap_available): ?>
    <h2 style="color: #34495e; font-size: 18px; font-weight: 600; margin: 20px 0 10px 0;">Endpoint Test</h2>
    <p><button id="test-soap-btn" onclick="testSoap()">Test SOAP Endpoint</button></p>
    <div id="soap-result" style="margin-top: 15px;"></div>
    <?php else: ?>
    <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">
        <strong style="color:#856404">⚠ WARNING</strong>
        SOAP endpoint test disabled - PHP extension not available.
    </div>
    <?php endif; ?>
</div>

<h1>WebDAV Protocol</h1>
<div style="background: white; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px; margin: 0 0 20px 0;">
    <h2 style="color: #34495e; font-size: 18px; font-weight: 600; margin: 20px 0 10px 0;">Endpoint Configuration</h2>
    <?php
        // Check if WebDAV dependencies are available
        // WebDAV uses Sabre\DAV\Server via Horde_Core_Factory_DavServer
        $webdav_available = class_exists('Sabre\\DAV\\Server');
        $webdav_status_color = $webdav_available ? 'green' : 'red';
        $webdav_status = $webdav_available ? 'Available' : 'Sabre DAV library not installed';
    ?>
    <ul>
        <li><strong>Endpoint URL:</strong> <code><?php echo htmlspecialchars($webroot . '/rpc.php') ?></code></li>
        <li><strong>Detection:</strong> PATH_INFO present or HTTP methods: PROPFIND, OPTIONS, PUT, DELETE, REPORT</li>
        <li><strong>Status:</strong> <span style="color:<?php echo $webdav_status_color ?>"><?php echo htmlspecialchars($webdav_status) ?></span></li>
        <?php if (!$webdav_available): ?>
        <li style="color:red">
            WebDAV support requires the <code>sabre/dav</code> library.<br />
            <strong>Fix:</strong> Install via Composer: <code>composer require sabre/dav</code>
        </li>
        <?php endif; ?>
    </ul>

    <div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">
        <strong>What this test does:</strong>
        <p style="margin: 10px 0 0 0;">
            Tests the WebDAV endpoint by sending a PROPFIND request to list root resources.
            This verifies:
        </p>
        <ul style="margin: 10px 0 0 20px; list-style: disc;">
            <li><strong>Endpoint is reachable:</strong> The WebDAV URL responds to requests</li>
            <li><strong>PROPFIND method supported:</strong> Server accepts WebDAV PROPFIND method</li>
            <li><strong>WebDAV protocol working:</strong> Server properly handles WebDAV requests</li>
            <li><strong>Returns XML multistatus:</strong> Validates proper WebDAV response format</li>
        </ul>
        <p style="margin: 10px 0 0 0;">
            <strong>Expected result:</strong> HTTP 207 Multi-Status with XML response containing DAV resources.
        </p>
        <p style="margin: 10px 0 0 0;">
            <strong>Note:</strong> WebDAV is detected by PROPFIND method or PATH_INFO (see rpc.php line 49-52).
        </p>
    </div>

    <?php if ($webdav_available): ?>
    <h2 style="color: #34495e; font-size: 18px; font-weight: 600; margin: 20px 0 10px 0;">Endpoint Test</h2>
    <p><button id="test-webdav-btn" onclick="testWebdav()">Test WebDAV Endpoint</button></p>
    <div id="webdav-result" style="margin-top: 15px;"></div>
    <?php else: ?>
    <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">
        <strong style="color:#856404">⚠ WARNING</strong>
        WebDAV endpoint test disabled - Sabre DAV library not available.
    </div>
    <?php endif; ?>
</div>

<?php require HORDE_TEMPLATES . '/test/protocols.inc'; ?>
<?php
        return ob_get_clean();
    }
}
