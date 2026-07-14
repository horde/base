<?php

/**
 * RPC processing script.
 *
 * Possible GET values:
 *   - requestMissingAuthorization: Whether or not to request authentication
 *                                  credentials if they are not already
 *                                  present.
 *   - wsdl: TODO
 *
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Util\Util;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/lib/Application.php';

// Since different RPC servers have different session requirements, we can't
// call appInit() until we know which server we are requesting. We  don't
// initialize the application until after we know the rpc server we want.
$input = $session_control = $cache_control = null;
$nocompress = false;
$params = [];

/* Look at the Content-type of the request, if it is available, to try
 * and determine what kind of request this is. */
if ((!empty($_SERVER['CONTENT_TYPE'])
     && (strpos($_SERVER['CONTENT_TYPE'], 'application/vnd.ms-sync.wbxml') !== false))
   || (strpos($_SERVER['REQUEST_URI'], 'Microsoft-Server-ActiveSync') !== false)
   || (stripos($_SERVER['REQUEST_URI'], 'autodiscover/autodiscover') !== false)) {
    /* ActiveSync Request */
    $conf['cookie']['path'] = '/Microsoft-Server-ActiveSync';
    // Avoid session timeout errors for short max_time values and potentially
    // long running EAS ping requests.
    $conf['session']['max_time'] = 0;
    $serverType = 'ActiveSync';
    $nocompress = true;
    $session_control = 'none';
    $cache_control = 'private';
} elseif (!empty($_SERVER['PATH_INFO'])
          || in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'PROPFIND', 'PUT', 'OPTIONS', 'REPORT'])) {
    $serverType = 'Webdav';
    $session_control = 'none';
} elseif (!empty($_SERVER['CONTENT_TYPE'])) {
    if (strpos($_SERVER['CONTENT_TYPE'], 'application/vnd.syncml+xml') !== false) {
        $serverType = 'Syncml';
        $nocompress = true;
        $session_control = 'none';
    } elseif (strpos($_SERVER['CONTENT_TYPE'], 'application/vnd.syncml+wbxml') !== false) {
        $serverType = 'Syncml_Wbxml';
        $nocompress = true;
        $session_control = 'none';
    } elseif (strpos($_SERVER['CONTENT_TYPE'], 'text/xml') !== false) {
        $input = file_get_contents('php://input');
        /* Check for SOAP namespace URI. */
        if (strpos($input, 'http://schemas.xmlsoap.org/soap/envelope/') !== false) {
            // SOAP requires soap extension
            if (!class_exists('SoapServer')) {
                header('HTTP/1.0 501 Not Implemented');
                header('Content-Type: text/plain; charset=utf-8');
                echo "SOAP Error: The soap PHP extension is not installed.\n\n";
                echo "The SOAP protocol requires the soap PHP extension.\n";
                echo "Install with: apt-get install php-soap\n";
                exit;
            }
            $serverType = 'Soap';
        } else {
            // XML-RPC requires xmlrpc extension
            if (!function_exists('xmlrpc_server_create')) {
                header('HTTP/1.0 501 Not Implemented');
                header('Content-Type: text/plain; charset=utf-8');
                echo "XML-RPC Error: The xmlrpc PHP extension is not installed.\n\n";
                echo "The XML-RPC protocol requires the xmlrpc PHP extension, which is deprecated and not installed.\n";
                echo "Install with: apt-get install php-xmlrpc (if available for your PHP version)\n\n";
                echo "Note: The xmlrpc extension is deprecated. Consider using JSON-RPC instead (Content-Type: application/json).\n";
                exit;
            }
            $serverType = 'Xmlrpc';
        }
    } elseif (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $serverType = 'Jsonrpc';
    } else {
        header('HTTP/1.0 501 Not Implemented');
        exit;
    }
} elseif ($_SERVER['QUERY_STRING'] && $_SERVER['QUERY_STRING'] == 'phpgw') {
    $serverType = 'Phpgw';
} else {
    $serverType = 'Webdav';
    $session_control = 'none';
}

/* Initialize Horde environment. */
Horde_Registry::appInit('horde', [
    'authentication' => 'none',
    'nocompress' => $nocompress,
    'session_control' => $session_control,
    'session_cache_limiter' => $cache_control,
    'nonotificationinit' => true,
]);

$request = $injector->getInstance('Horde_Controller_Request');
$logger = $injector->getInstance(LoggerInterface::class);

$params['logger'] = $injector->getInstance('Horde_Log_Logger');

/* Check to see if we want to exit if required credentials are not
 * present. */
if (($ra = Util::getGet('requestMissingAuthorization')) !== null) {
    $params['requestMissingAuthorization'] = $ra;
}

/* Driver specific tasks that require Horde environment. */
switch ($serverType) {
    case 'ActiveSync':
        // Check if AS is enabled. Note that we can't check the user perms for it
        // here since the user is not yet logged into horde at this point.
        if (empty($conf['activesync']['enabled'])) {
            exit;
        }
        // Check if ActiveSync library is actually installed
        if (!class_exists('Horde_ActiveSync')) {
            header('HTTP/1.0 500 Internal Server Error');
            header('Content-Type: text/plain; charset=utf-8');
            echo "ActiveSync Error: Horde_ActiveSync library is not installed.\n\n";
            echo "ActiveSync is enabled in configuration but the required library is missing.\n";
            echo "Install the ActiveSync component to enable this functionality.\n";
            exit;
        }
        $params['server'] = $injector->getInstance('Horde_ActiveSyncServer');
        // Stream Sync response bodies incrementally (chunked) instead of
        // buffering the full WBXML; see horde/ActiveSync#83.
        $params['streaming'] = !empty($conf['activesync']['sync']['streaming']);
        $params['requireAuthorization'] = true;
        break;

    case 'Soap':
        $serverVars = $request->getServerVars();
        if (!$serverVars['REQUEST_METHOD']
            || ($serverVars['REQUEST_METHOD'] != 'POST')) {
            $params['requireAuthorization'] = false;
            $input = (Util::getGet('wsdl') === null)
                ? 'disco'
                : 'wsdl';
        }
        break;
}

/* Load the RPC backend based on $serverType. */
try {
    $server = Horde_Rpc::factory($serverType, $request, $params);
} catch (Horde_Rpc_Exception $e) {
    $logger->error($e->getMessage(), ['exception' => $e]);
    header('HTTP/1.1 501 Not Implemented');
    exit;
}

// Let the backend check authentication. By default, we look for HTTP
// basic authentication against Horde, but backends can override this
// as needed. Must reset the authentication argument since we delegate
// auth to the RPC server.
$registry->setAuthenticationSetting(
    (array_key_exists('requireAuthorization', $params) && $params['requireAuthorization'] === false)
    ? 'none'
    : 'Authenticate'
);

try {
    $server->authorize();
} catch (Horde_Rpc_Exception $e) {
    $logger->error($e->getMessage(), ['exception' => $e]);
    header('HTTP/1.0 500 Internal Server Error');
    echo $e->getMessage();
    exit;
}


/* Get the server's response. We call $server->getInput() to allow
 * backends to handle input processing differently. */
if (is_null($input)) {
    try {
        $input = $server->getInput();
    } catch (Horde_Rpc_Exception $e) {
        $logger->error($e->getMessage(), ['exception' => $e]);
        header('HTTP/1.0 500 Internal Server Error');
        echo $e->getMessage();
        exit;
    }
}

try {
    $out = $server->getResponse($input);
} catch (Horde_Rpc_Exception $e) {
    $logger->error($e->getMessage(), ['exception' => $e]);
    header('HTTP/1.0 500 Internal Server Error');
    echo $e->getMessage();
    exit;
}


if ($out instanceof PEAR_Error) {
    header('HTTP/1.0 500 Internal Server Error');
    echo $out->getMessage();
    exit;
}

// Allow backends to determine how and when to send output.
$server->sendOutput($out);
