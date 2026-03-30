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

<script type="text/javascript">
    const activesyncUrl = <?php echo $activesync_url_json ?>;
    const rpcUrl = <?php echo json_encode($webroot . '/rpc.php') ?>;

    function testActiveSync() {
        const btn = document.getElementById('test-activesync-btn');
        const resultDiv = document.getElementById('activesync-result');

        if (!activesyncUrl) {
            resultDiv.innerHTML = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;"><strong style="color:#e74c3c">Error:</strong> ActiveSync URL not configured</div>';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Testing...';
        resultDiv.innerHTML = '<div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">Testing ActiveSync endpoint...<br /><strong>URL:</strong> <code>' + escapeHtml(activesyncUrl) + '</code></div>';

        const xhr = new XMLHttpRequest();
        xhr.timeout = 10000; // 10 second timeout

        xhr.onload = function() {
            let html;
            const protocolVersions = xhr.getResponseHeader('MS-ASProtocolVersions') || 'not provided';
            const protocolCommands = xhr.getResponseHeader('MS-ASProtocolCommands') || 'not provided';
            const contentType = xhr.getResponseHeader('Content-Type') || 'not provided';
            const server = xhr.getResponseHeader('Server') || 'not provided';

            if (xhr.status === 200) {
                const responseSize = xhr.responseText ? xhr.responseText.length : 0;
                const isHtml = contentType.toLowerCase().includes('text/html');

                if (protocolVersions !== 'not provided' && protocolCommands !== 'not provided') {
                    // Perfect - ActiveSync is properly configured
                    html = '<div style="background-color: #d4edda; border-left: 4px solid #27ae60; padding: 15px; margin: 15px 0;">'
                        + '<strong style="color:#27ae60">✓ PASS</strong> ActiveSync endpoint is working correctly<br /><br />'
                        + '<strong>Endpoint:</strong> <code>' + escapeHtml(activesyncUrl) + '</code><br />'
                        + '<strong>HTTP Status:</strong> ' + xhr.status + ' OK<br />'
                        + '<strong>Protocol Versions:</strong> <code>' + escapeHtml(protocolVersions) + '</code><br />'
                        + '<strong>Protocol Commands:</strong> <code>' + escapeHtml(protocolCommands) + '</code><br />'
                        + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />'
                        + '<strong>Response Size:</strong> ' + responseSize + ' bytes<br />'
                        + '<strong>Server:</strong> <code>' + escapeHtml(server) + '</code><br /><br />'
                        + '<em>The ActiveSync endpoint is properly configured and responding with the expected protocol headers. '
                        + 'Clients should be able to discover and connect to this server.</em>'
                        + '</div>';
                } else if (isHtml && responseSize > 100) {
                    // Suspicious - returning HTML instead of ActiveSync response
                    html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                        + '<strong style="color:#e74c3c">✗ FAIL</strong> Wrong response type - endpoint is returning HTML, not ActiveSync<br /><br />'
                        + '<strong>Endpoint:</strong> <code>' + escapeHtml(activesyncUrl) + '</code><br />'
                        + '<strong>HTTP Status:</strong> ' + xhr.status + ' OK<br />'
                        + '<strong>MS-ASProtocolVersions:</strong> <code>' + escapeHtml(protocolVersions) + '</code><br />'
                        + '<strong>MS-ASProtocolCommands:</strong> <code>' + escapeHtml(protocolCommands) + '</code><br />'
                        + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code> <strong style="color:#e74c3c">← WRONG (should not be HTML)</strong><br />'
                        + '<strong>Response Size:</strong> ' + responseSize + ' bytes<br />'
                        + '<strong>Server:</strong> <code>' + escapeHtml(server) + '</code><br /><br />'
                        + '<strong>Problem:</strong> A rogue controller or wrong route is intercepting the ActiveSync endpoint and returning HTML instead of ActiveSync protocol responses<br />'
                        + '<strong>Common causes:</strong><br />'
                        + '<ul style="margin: 5px 0 0 20px; list-style: disc;">'
                        + '<li>URL routing misconfiguration sending requests to wrong handler</li>'
                        + '<li>Another application or framework intercepting the /rpc.php path</li>'
                        + '<li>.htaccess rewrite rules sending to wrong endpoint</li>'
                        + '<li>ActiveSync handler not properly registered in registry</li>'
                        + '</ul><br />'
                        + '<strong>Fix:</strong> Check routing configuration and verify rpc.php is handled by the correct ActiveSync handler'
                        + '</div>';
                } else {
                    // Endpoint responds but missing required headers
                    html = '<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">'
                        + '<strong style="color:#856404">⚠ PARTIAL</strong> ActiveSync endpoint is reachable but missing protocol headers<br /><br />'
                        + '<strong>Endpoint:</strong> <code>' + escapeHtml(activesyncUrl) + '</code><br />'
                        + '<strong>HTTP Status:</strong> ' + xhr.status + ' OK<br />'
                        + '<strong>MS-ASProtocolVersions:</strong> <code>' + escapeHtml(protocolVersions) + '</code><br />'
                        + '<strong>MS-ASProtocolCommands:</strong> <code>' + escapeHtml(protocolCommands) + '</code><br />'
                        + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />'
                        + '<strong>Response Size:</strong> ' + responseSize + ' bytes<br />'
                        + '<strong>Server:</strong> <code>' + escapeHtml(server) + '</code><br /><br />'
                        + '<strong>Problem:</strong> The endpoint responds to OPTIONS but does not include the required ActiveSync protocol headers<br />'
                        + '<strong>Required headers:</strong><br />'
                        + '<ul style="margin: 5px 0 0 20px; list-style: disc;">'
                        + '<li><code>MS-ASProtocolVersions</code> - Supported protocol versions (e.g., "2.5,12.0,12.1,14.0,14.1,16.0")</li>'
                        + '<li><code>MS-ASProtocolCommands</code> - Supported commands (e.g., "Sync,FolderSync,GetItemEstimate,...")</li>'
                        + '</ul><br />'
                        + '<strong>Fix:</strong> Check ActiveSync configuration and ensure the endpoint handler is properly configured'
                        + '</div>';
                }
            } else if (xhr.status === 401) {
                // Unauthorized - this might be expected behavior
                html = '<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#856404">⚠ INFO</strong> ActiveSync endpoint returned HTTP 401 (Unauthorized)<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(activesyncUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> 401 Unauthorized<br />'
                    + '<strong>MS-ASProtocolVersions:</strong> <code>' + escapeHtml(protocolVersions) + '</code><br />'
                    + '<strong>MS-ASProtocolCommands:</strong> <code>' + escapeHtml(protocolCommands) + '</code><br />'
                    + '<strong>Server:</strong> <code>' + escapeHtml(server) + '</code><br /><br />'
                    + '<strong>Note:</strong> HTTP 401 is acceptable if the server requires authentication for OPTIONS requests. '
                    + 'Check if the required protocol headers are present above. Some ActiveSync implementations '
                    + 'require credentials even for OPTIONS discovery.'
                    + '</div>';
            } else if (xhr.status === 403) {
                html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#e74c3c">✗ FAIL</strong> ActiveSync endpoint returned HTTP 403 (Forbidden)<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(activesyncUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> 403 Forbidden<br />'
                    + '<strong>Server:</strong> <code>' + escapeHtml(server) + '</code><br /><br />'
                    + '<strong>Problem:</strong> The endpoint is blocking access<br />'
                    + '<strong>Common causes:</strong><br />'
                    + '<ul style="margin: 5px 0 0 20px; list-style: disc;">'
                    + '<li>.htaccess rules blocking access to rpc.php</li>'
                    + '<li>Web server configuration denying access</li>'
                    + '<li>Security module (mod_security, fail2ban) blocking request</li>'
                    + '</ul><br />'
                    + '<strong>Fix:</strong> Check web server configuration and .htaccess rules'
                    + '</div>';
            } else if (xhr.status === 404) {
                html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#e74c3c">✗ FAIL</strong> ActiveSync endpoint not found (HTTP 404)<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(activesyncUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> 404 Not Found<br />'
                    + '<strong>Server:</strong> <code>' + escapeHtml(server) + '</code><br /><br />'
                    + '<strong>Problem:</strong> The ActiveSync endpoint does not exist at this URL<br />'
                    + '<strong>Common causes:</strong><br />'
                    + '<ul style="margin: 5px 0 0 20px; list-style: disc;">'
                    + '<li>Incorrect endpoint URL configuration</li>'
                    + '<li>rpc.php file missing or not accessible</li>'
                    + '<li>URL rewriting rules not configured</li>'
                    + '</ul><br />'
                    + '<strong>Fix:</strong> Verify rpc.php exists and check ActiveSync configuration'
                    + '</div>';
            } else if (xhr.status === 500 || xhr.status === 502 || xhr.status === 503) {
                const responseText = xhr.responseText || '';
                const hasErrorMessage = responseText.length > 0 && responseText.length < 1000;

                html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#e74c3c">✗ FAIL</strong> ActiveSync endpoint returned HTTP ' + xhr.status + ' (Server Error)<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(activesyncUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> ' + xhr.status + '<br />'
                    + '<strong>Server:</strong> <code>' + escapeHtml(server) + '</code><br />';

                if (hasErrorMessage) {
                    html += '<strong>Error Message:</strong><br />'
                        + '<pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word;">'
                        + escapeHtml(responseText)
                        + '</pre><br />';
                }

                html += '<strong>Problem:</strong> The endpoint encountered an internal error<br />'
                    + '<strong>Common causes:</strong><br />'
                    + '<ul style="margin: 5px 0 0 20px; list-style: disc;">'
                    + '<li>ActiveSync app not installed or missing dependencies</li>'
                    + '<li>PHP error in ActiveSync handler initialization</li>'
                    + '<li>Database connection failure</li>'
                    + '<li>Missing required PHP extensions</li>'
                    + '<li>Configuration error in conf.php</li>'
                    + '</ul><br />'
                    + '<strong>Fix:</strong> Check web server error log (typically <code>/var/log/apache2/error.log</code> or <code>/var/log/httpd/error_log</code>) and Horde logs'
                    + '</div>';
            } else {
                html = '<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#856404">⚠ UNEXPECTED</strong> ActiveSync endpoint returned HTTP ' + xhr.status + '<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(activesyncUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> ' + xhr.status + '<br />'
                    + '<strong>MS-ASProtocolVersions:</strong> <code>' + escapeHtml(protocolVersions) + '</code><br />'
                    + '<strong>MS-ASProtocolCommands:</strong> <code>' + escapeHtml(protocolCommands) + '</code><br />'
                    + '<strong>Server:</strong> <code>' + escapeHtml(server) + '</code>'
                    + '</div>';
            }
            resultDiv.innerHTML = html;
            btn.disabled = false;
            btn.textContent = 'Re-test ActiveSync Endpoint';
        };

        xhr.onerror = function() {
            const html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                + '<strong style="color:#e74c3c">✗ FAIL</strong> Network error - could not connect to ActiveSync endpoint<br /><br />'
                + '<strong>Endpoint:</strong> <code>' + escapeHtml(activesyncUrl) + '</code><br /><br />'
                + '<strong>Problem:</strong> The browser could not establish a connection<br />'
                + '<strong>Common causes:</strong><br />'
                + '<ul style="margin: 5px 0 0 20px; list-style: disc;">'
                + '<li>Incorrect hostname or port in endpoint URL</li>'
                + '<li>Network connectivity issue</li>'
                + '<li>Firewall blocking connection</li>'
                + '<li>CORS policy blocking cross-origin requests</li>'
                + '</ul>'
                + '</div>';
            resultDiv.innerHTML = html;
            btn.disabled = false;
            btn.textContent = 'Re-test ActiveSync Endpoint';
        };

        xhr.ontimeout = function() {
            const html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                + '<strong style="color:#e74c3c">✗ FAIL</strong> Request timeout - ActiveSync endpoint not responding<br /><br />'
                + '<strong>Endpoint:</strong> <code>' + escapeHtml(activesyncUrl) + '</code><br />'
                + '<strong>Timeout:</strong> 10 seconds<br /><br />'
                + '<strong>Problem:</strong> The request timed out after 10 seconds<br />'
                + '<strong>Fix:</strong> Check if the web server is running and accessible'
                + '</div>';
            resultDiv.innerHTML = html;
            btn.disabled = false;
            btn.textContent = 'Re-test ActiveSync Endpoint';
        };

        xhr.open('OPTIONS', activesyncUrl, true);
        // Set Content-Type to trigger ActiveSync detection in rpc.php (line 36-37)
        xhr.setRequestHeader('Content-Type', 'application/vnd.ms-sync.wbxml');
        xhr.send();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function testJsonRpc() {
        const btn = document.getElementById('test-jsonrpc-btn');
        const resultDiv = document.getElementById('jsonrpc-result');

        btn.disabled = true;
        btn.textContent = 'Testing...';
        resultDiv.innerHTML = '<div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">Testing JSON-RPC endpoint...<br /><strong>URL:</strong> <code>' + escapeHtml(rpcUrl) + '</code></div>';

        const requestBody = JSON.stringify({
            jsonrpc: '2.0',
            method: 'horde.listApps',
            params: [],
            id: 1
        });

        const xhr = new XMLHttpRequest();
        xhr.timeout = 10000;

        xhr.onload = function() {
            let html;
            const contentType = xhr.getResponseHeader('Content-Type') || 'not provided';

            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);

                    if (response.error) {
                        html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                            + '<strong style="color:#e74c3c">✗ FAIL</strong> JSON-RPC returned an error<br /><br />'
                            + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                            + '<strong>HTTP Status:</strong> ' + xhr.status + ' OK<br />'
                            + '<strong>Error Code:</strong> ' + (response.error.code || 'none') + '<br />'
                            + '<strong>Error Message:</strong> ' + escapeHtml(response.error.message || 'Unknown error') + '<br />'
                            + '</div>';
                    } else if (response.result) {
                        const apis = Array.isArray(response.result) ? response.result : [];
                        html = '<div style="background-color: #d4edda; border-left: 4px solid #27ae60; padding: 15px; margin: 15px 0;">'
                            + '<strong style="color:#27ae60">✓ PASS</strong> JSON-RPC endpoint is working correctly<br /><br />'
                            + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                            + '<strong>HTTP Status:</strong> ' + xhr.status + ' OK<br />'
                            + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />'
                            + '<strong>Available APIs:</strong> ' + (apis.length > 0 ? '<code>' + escapeHtml(apis.join(', ')) + '</code>' : 'None') + '<br /><br />'
                            + '<em>The JSON-RPC endpoint is properly configured and responding to API calls.</em>'
                            + '</div>';
                    } else {
                        html = '<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">'
                            + '<strong style="color:#856404">⚠ UNEXPECTED</strong> JSON-RPC response missing result<br /><br />'
                            + '<strong>Response:</strong><br /><pre style="background: #f8f9fa; padding: 10px; overflow-x: auto;">' + escapeHtml(xhr.responseText.substring(0, 500)) + '</pre>'
                            + '</div>';
                    }
                } catch (e) {
                    html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                        + '<strong style="color:#e74c3c">✗ FAIL</strong> Invalid JSON response<br /><br />'
                        + '<strong>Parse Error:</strong> ' + escapeHtml(e.message) + '<br />'
                        + '<strong>Response:</strong><br /><pre style="background: #f8f9fa; padding: 10px; overflow-x: auto;">' + escapeHtml(xhr.responseText.substring(0, 500)) + '</pre>'
                        + '</div>';
                }
            } else {
                const responseText = xhr.responseText || '';
                html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#e74c3c">✗ FAIL</strong> JSON-RPC endpoint returned HTTP ' + xhr.status + '<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> ' + xhr.status + '<br />'
                    + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />';

                if (responseText.length > 0) {
                    html += '<strong>Error Response:</strong><br />'
                        + '<pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto;">'
                        + escapeHtml(responseText.substring(0, 2000))
                        + '</pre>';
                }

                html += '</div>';
            }

            resultDiv.innerHTML = html;
            btn.disabled = false;
            btn.textContent = 'Re-test JSON-RPC Endpoint';
        };

        xhr.onerror = function() {
            resultDiv.innerHTML = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                + '<strong style="color:#e74c3c">✗ FAIL</strong> Network error<br /><br />'
                + '<strong>Problem:</strong> Could not connect to endpoint'
                + '</div>';
            btn.disabled = false;
            btn.textContent = 'Re-test JSON-RPC Endpoint';
        };

        xhr.ontimeout = function() {
            resultDiv.innerHTML = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                + '<strong style="color:#e74c3c">✗ FAIL</strong> Request timeout (10 seconds)'
                + '</div>';
            btn.disabled = false;
            btn.textContent = 'Re-test JSON-RPC Endpoint';
        };

        xhr.open('POST', rpcUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(requestBody);
    }

    function testXmlRpc() {
        const btn = document.getElementById('test-xmlrpc-btn');
        const resultDiv = document.getElementById('xmlrpc-result');

        btn.disabled = true;
        btn.textContent = 'Testing...';
        resultDiv.innerHTML = '<div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">Testing XML-RPC endpoint...<br /><strong>URL:</strong> <code>' + escapeHtml(rpcUrl) + '</code></div>';

        const requestBody = '<?xml version="1.0" encoding="UTF-8"?>'
            + '<methodCall>'
            + '<methodName>horde.listApps</methodName>'
            + '<params></params>'
            + '</methodCall>';

        const xhr = new XMLHttpRequest();
        xhr.timeout = 10000;

        xhr.onload = function() {
            let html;
            const contentType = xhr.getResponseHeader('Content-Type') || 'not provided';

            if (xhr.status === 200) {
                const responseText = xhr.responseText;
                const isXml = contentType.toLowerCase().includes('text/xml') || contentType.toLowerCase().includes('application/xml');

                if (isXml && responseText.includes('<methodResponse>')) {
                    // Try to extract the result
                    const hasError = responseText.includes('<fault>');

                    if (hasError) {
                        html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                            + '<strong style="color:#e74c3c">✗ FAIL</strong> XML-RPC returned a fault<br /><br />'
                            + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                            + '<strong>HTTP Status:</strong> ' + xhr.status + ' OK<br />'
                            + '<strong>Response:</strong><br /><pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap;">' + escapeHtml(responseText.substring(0, 1000)) + '</pre>'
                            + '</div>';
                    } else {
                        // Extract array values from response
                        const valueMatches = responseText.match(/<value><string>([^<]+)<\/string><\/value>/g) || [];
                        const apis = valueMatches.map(m => m.replace(/<value><string>([^<]+)<\/string><\/value>/, '$1'));

                        html = '<div style="background-color: #d4edda; border-left: 4px solid #27ae60; padding: 15px; margin: 15px 0;">'
                            + '<strong style="color:#27ae60">✓ PASS</strong> XML-RPC endpoint is working correctly<br /><br />'
                            + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                            + '<strong>HTTP Status:</strong> ' + xhr.status + ' OK<br />'
                            + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />'
                            + '<strong>Available APIs:</strong> ' + (apis.length > 0 ? '<code>' + escapeHtml(apis.join(', ')) + '</code>' : 'None') + '<br /><br />'
                            + '<em>The XML-RPC endpoint is properly configured and responding to API calls.</em>'
                            + '</div>';
                    }
                } else {
                    html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                        + '<strong style="color:#e74c3c">✗ FAIL</strong> Invalid XML-RPC response<br /><br />'
                        + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />'
                        + '<strong>Response:</strong><br /><pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap;">' + escapeHtml(responseText.substring(0, 500)) + '</pre>'
                        + '</div>';
                }
            } else {
                const responseText = xhr.responseText || '';
                html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#e74c3c">✗ FAIL</strong> XML-RPC endpoint returned HTTP ' + xhr.status + '<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> ' + xhr.status + '<br />'
                    + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />';

                if (responseText.length > 0) {
                    html += '<strong>Error Response:</strong><br />'
                        + '<pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto;">'
                        + escapeHtml(responseText.substring(0, 2000))
                        + '</pre>';
                }

                html += '</div>';
            }

            resultDiv.innerHTML = html;
            btn.disabled = false;
            btn.textContent = 'Re-test XML-RPC Endpoint';
        };

        xhr.onerror = function() {
            resultDiv.innerHTML = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                + '<strong style="color:#e74c3c">✗ FAIL</strong> Network error<br /><br />'
                + '<strong>Problem:</strong> Could not connect to endpoint'
                + '</div>';
            btn.disabled = false;
            btn.textContent = 'Re-test XML-RPC Endpoint';
        };

        xhr.ontimeout = function() {
            resultDiv.innerHTML = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                + '<strong style="color:#e74c3c">✗ FAIL</strong> Request timeout (10 seconds)'
                + '</div>';
            btn.disabled = false;
            btn.textContent = 'Re-test XML-RPC Endpoint';
        };

        xhr.open('POST', rpcUrl, true);
        xhr.setRequestHeader('Content-Type', 'text/xml');
        xhr.send(requestBody);
    }

    function testWebdav() {
        const btn = document.getElementById('test-webdav-btn');
        const resultDiv = document.getElementById('webdav-result');

        btn.disabled = true;
        btn.textContent = 'Testing...';
        resultDiv.innerHTML = '<div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">Testing WebDAV endpoint...<br /><strong>URL:</strong> <code>' + escapeHtml(rpcUrl) + '</code></div>';

        const requestBody = '<?xml version="1.0" encoding="utf-8"?>'
            + '<D:propfind xmlns:D="DAV:">'
            + '<D:prop>'
            + '<D:resourcetype/>'
            + '<D:displayname/>'
            + '</D:prop>'
            + '</D:propfind>';

        const xhr = new XMLHttpRequest();
        xhr.timeout = 10000;

        xhr.onload = function() {
            let html;
            const contentType = xhr.getResponseHeader('Content-Type') || 'not provided';
            const davHeader = xhr.getResponseHeader('DAV') || 'not provided';

            if (xhr.status === 207) {
                // HTTP 207 Multi-Status is the expected WebDAV response
                const responseText = xhr.responseText;
                const isXml = contentType.toLowerCase().includes('text/xml') || contentType.toLowerCase().includes('application/xml');

                if (isXml && (responseText.includes('<d:multistatus') || responseText.includes('<D:multistatus'))) {
                    html = '<div style="background-color: #d4edda; border-left: 4px solid #27ae60; padding: 15px; margin: 15px 0;">'
                        + '<strong style="color:#27ae60">✓ PASS</strong> WebDAV endpoint is working correctly<br /><br />'
                        + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                        + '<strong>HTTP Status:</strong> 207 Multi-Status<br />'
                        + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />'
                        + '<strong>DAV Header:</strong> <code>' + escapeHtml(davHeader) + '</code><br /><br />'
                        + '<em>The WebDAV endpoint is properly configured and responding with the expected multistatus XML response.</em>'
                        + '</div>';
                } else {
                    html = '<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">'
                        + '<strong style="color:#856404">⚠ UNEXPECTED</strong> WebDAV returned 207 but invalid response format<br /><br />'
                        + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />'
                        + '<strong>Response:</strong><br /><pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap;">' + escapeHtml(responseText.substring(0, 1000)) + '</pre>'
                        + '</div>';
                }
            } else if (xhr.status === 200) {
                html = '<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#856404">⚠ UNEXPECTED</strong> WebDAV returned HTTP 200 (expected 207 Multi-Status)<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> 200 OK<br />'
                    + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />'
                    + '<strong>Problem:</strong> PROPFIND should return 207 Multi-Status, not 200 OK'
                    + '</div>';
            } else if (xhr.status === 401) {
                html = '<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#856404">⚠ INFO</strong> WebDAV endpoint requires authentication<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> 401 Unauthorized<br />'
                    + '<strong>Note:</strong> WebDAV may require authentication. This is acceptable if configured to require credentials.'
                    + '</div>';
            } else if (xhr.status === 404) {
                html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#e74c3c">✗ FAIL</strong> WebDAV endpoint not found<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> 404 Not Found<br />'
                    + '<strong>Problem:</strong> The endpoint does not exist or WebDAV routing is not configured'
                    + '</div>';
            } else {
                const responseText = xhr.responseText || '';
                html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#e74c3c">✗ FAIL</strong> WebDAV endpoint returned HTTP ' + xhr.status + '<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> ' + xhr.status + '<br />'
                    + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />';

                if (responseText.length > 0) {
                    html += '<strong>Error Response:</strong><br />'
                        + '<pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto;">'
                        + escapeHtml(responseText.substring(0, 2000))
                        + '</pre>';
                }

                html += '</div>';
            }

            resultDiv.innerHTML = html;
            btn.disabled = false;
            btn.textContent = 'Re-test WebDAV Endpoint';
        };

        xhr.onerror = function() {
            resultDiv.innerHTML = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                + '<strong style="color:#e74c3c">✗ FAIL</strong> Network error<br /><br />'
                + '<strong>Problem:</strong> Could not connect to endpoint'
                + '</div>';
            btn.disabled = false;
            btn.textContent = 'Re-test WebDAV Endpoint';
        };

        xhr.ontimeout = function() {
            resultDiv.innerHTML = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                + '<strong style="color:#e74c3c">✗ FAIL</strong> Request timeout (10 seconds)'
                + '</div>';
            btn.disabled = false;
            btn.textContent = 'Re-test WebDAV Endpoint';
        };

        xhr.open('PROPFIND', rpcUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/xml');
        xhr.setRequestHeader('Depth', '0');
        xhr.send(requestBody);
    }

    function testSoap() {
        const btn = document.getElementById('test-soap-btn');
        const resultDiv = document.getElementById('soap-result');

        btn.disabled = true;
        btn.textContent = 'Testing...';
        resultDiv.innerHTML = '<div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">Testing SOAP endpoint...<br /><strong>URL:</strong> <code>' + escapeHtml(rpcUrl) + '</code></div>';

        const requestBody = '<?xml version="1.0" encoding="UTF-8"?>'
            + '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" '
            + 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            + 'xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
            + '<soap:Body>'
            + '<horde.listApps />'
            + '</soap:Body>'
            + '</soap:Envelope>';

        const xhr = new XMLHttpRequest();
        xhr.timeout = 10000;

        xhr.onload = function() {
            let html;
            const contentType = xhr.getResponseHeader('Content-Type') || 'not provided';

            if (xhr.status === 200) {
                const responseText = xhr.responseText;
                const isXml = contentType.toLowerCase().includes('text/xml') || contentType.toLowerCase().includes('application/xml');

                if (isXml && (responseText.includes('<SOAP-ENV:Envelope') || responseText.includes('<soap:Envelope'))) {
                    const hasFault = responseText.includes('<SOAP-ENV:Fault') || responseText.includes('<soap:Fault');

                    if (hasFault) {
                        html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                            + '<strong style="color:#e74c3c">✗ FAIL</strong> SOAP returned a fault<br /><br />'
                            + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                            + '<strong>HTTP Status:</strong> ' + xhr.status + ' OK<br />'
                            + '<strong>Response:</strong><br /><pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap;">' + escapeHtml(responseText.substring(0, 1000)) + '</pre>'
                            + '</div>';
                    } else {
                        html = '<div style="background-color: #d4edda; border-left: 4px solid #27ae60; padding: 15px; margin: 15px 0;">'
                            + '<strong style="color:#27ae60">✓ PASS</strong> SOAP endpoint is working correctly<br /><br />'
                            + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                            + '<strong>HTTP Status:</strong> ' + xhr.status + ' OK<br />'
                            + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br /><br />'
                            + '<strong>Response Preview:</strong><br /><pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap;">' + escapeHtml(responseText.substring(0, 500)) + '</pre><br />'
                            + '<em>The SOAP endpoint is properly configured and responding to API calls.</em>'
                            + '</div>';
                    }
                } else {
                    html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                        + '<strong style="color:#e74c3c">✗ FAIL</strong> Invalid SOAP response<br /><br />'
                        + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />'
                        + '<strong>Response:</strong><br /><pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap;">' + escapeHtml(responseText.substring(0, 500)) + '</pre>'
                        + '</div>';
                }
            } else {
                const responseText = xhr.responseText || '';
                html = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                    + '<strong style="color:#e74c3c">✗ FAIL</strong> SOAP endpoint returned HTTP ' + xhr.status + '<br /><br />'
                    + '<strong>Endpoint:</strong> <code>' + escapeHtml(rpcUrl) + '</code><br />'
                    + '<strong>HTTP Status:</strong> ' + xhr.status + '<br />'
                    + '<strong>Content-Type:</strong> <code>' + escapeHtml(contentType) + '</code><br />';

                if (responseText.length > 0) {
                    html += '<strong>Error Response:</strong><br />'
                        + '<pre style="background: #f8f9fa; padding: 10px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto;">'
                        + escapeHtml(responseText.substring(0, 2000))
                        + '</pre>';
                }

                html += '</div>';
            }

            resultDiv.innerHTML = html;
            btn.disabled = false;
            btn.textContent = 'Re-test SOAP Endpoint';
        };

        xhr.onerror = function() {
            resultDiv.innerHTML = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                + '<strong style="color:#e74c3c">✗ FAIL</strong> Network error<br /><br />'
                + '<strong>Problem:</strong> Could not connect to endpoint'
                + '</div>';
            btn.disabled = false;
            btn.textContent = 'Re-test SOAP Endpoint';
        };

        xhr.ontimeout = function() {
            resultDiv.innerHTML = '<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">'
                + '<strong style="color:#e74c3c">✗ FAIL</strong> Request timeout (10 seconds)'
                + '</div>';
            btn.disabled = false;
            btn.textContent = 'Re-test SOAP Endpoint';
        };

        xhr.open('POST', rpcUrl, true);
        xhr.setRequestHeader('Content-Type', 'text/xml');
        xhr.setRequestHeader('SOAPAction', 'horde.listApps');
        xhr.send(requestBody);
    }
</script>
<?php
        return ob_get_clean();
    }
}
