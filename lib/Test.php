<?php

/**
 * The Horde_Test:: class provides functions used in the test scripts
 * used in the various applications (test.php).
 *
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Jon Parise <jon@horde.org>
 * @author   Brent J. Nordquist <bjn@horde.org>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

/* If gettext is not loaded, define a dummy _() function so that
 * including any file with gettext strings won't cause a fatal error,
 * causing test.php to return a blank page. */
if (!function_exists('_')) {
    function _($s)
    {
        return $s;
    }
}

class Horde_Test
{
    /**
     * The PHP version of the system.
     *
     * @var array
     */
    protected $_phpver;

    /**
     * Supported versions of PHP.
     *
     * @var array
     */
    protected $_supported = [
        '7.4',
        '8.0',
        '8.1',
        '8.2',
    ];

    /**
     * The module list
     * <pre>
     * KEY:   extension name
     * VALUE: Either the description or an array with the following entries:
     *        descrip: (string) Module description
     *        error: (string) Error message
     *        fatal: (boolean) Is missing extension fatal?
     *        function: (string) Reference to function to run. If function
     *                  returns boolean false, error message will be output.
     *                  If function returns a string, this error message
     *                  will be used.
     *        phpver: (string) The PHP version above which to do the test
     * </pre>
     *
     * @var array
     */
    protected $_moduleList = [
        'ctype' => [
            'descrip' => 'Ctype Support',
            'error' => 'The ctype functions are required by various Horde libraries. Don\t compile PHP with <code>--disable-all/--disable-ctype</code>.',
            'fatal' => true,
        ],
        'dom' => [
            'descrip' => 'DOM XML Support',
            'error' => 'Horde will not run without the dom extension. Don\'t compile PHP with <code>--disable-all/--disable-dom</code>.',
            'fatal' => true,
        ],
        'fileinfo' => [
            'descrip' => 'MIME Magic Support (fileinfo)',
            'error' => 'The fileinfo extension is used to provide MIME Magic scanning on unknown data. Don\'t compile PHP with <code>--disable-all/--disable-fileinfo</code>.',
        ],
        'fileinfo_check' => [
            'descrip' => 'MIME Magic Support (fileinfo) - Configuration',
            'error' => 'The fileinfo extension could not open the default MIME Magic database location. You will need to manually specify the MIME Magic database location in the config file.',
            'function' => '_checkFileinfo',
        ],
        'ftp' => [
            'descrip' => 'FTP Support',
            'error' => 'FTP support is only required if you want to authenticate against an FTP server, upload your configuration files with FTP, or use an FTP server for file storage. Compile PHP with <code>--enable-ftp</code> to ensure the extension is active on your server.',
        ],
        'gd' => [
            'descrip' => 'GD Support',
            'error' => 'Horde will use the GD extension to perform manipulations on image data (compile PHP with <code>--with-gd</code>). It is recommended to use the PECL imagick library instead over this extension.',
        ],
        'gettext' => [
            'descrip' => 'Gettext Support',
            'error' => 'Horde will not run without gettext support. Compile PHP with <code>--with-gettext</code>.',
            'fatal' => true,
        ],
        'geoip' => [
            'descrip' => 'GeoIP Support (PECL extension)',
            'error' => 'Horde can optionally use the GeoIP extension to provide faster country name lookups.',
        ],
        'hash' => [
            'descrip' => 'Hash Support',
            'error' => 'Horde will not run without the hash extension. Don\'t compile PHP with <code>--disable-all/--disable-hash</code>.',
            'fatal' => true,
        ],
        'horde_lz4/lzf' => [
            'descrip' => 'LZ4/LZF Compression Support (PECL extension)',
            'error' => 'If the horde_lz4 or lzf PECL extensions are available, Horde can perform real-time compression on cached data to optimize storage resources. It is recommended to use horde_lz4, as its compression speed is twice as fast as the lzf extension\'s.',
            'function' => '_checkLzCompression',
        ],
        'iconv' => [
            'descrip' => 'Iconv Support',
            'error' => 'If you want to take full advantage of Horde\'s localization features and character set support, you will need the iconv extension. Don\t compile PHP with <code>--disable-all/--disable-iconv</code>.',
        ],
        'iconv_libiconv' => [
            'descrip' => 'GNU Iconv Support',
            'error' => 'For best results make sure the iconv extension is linked against GNU libiconv.',
            'function' => '_checkIconvImplementation',
        ],
        'imagick' => [
            'descrip' => 'Imagick (PECL extension)',
            'error' => 'Horde can make use of the Imagick library to manipulate images. It is highly recommended to use the PECL extension (although, alternatively, Horde can be configured to use the convert command line utility instead).',
        ],
        'json' => [
            'descrip' => 'JSON Support',
            'error' => 'Horde will not run without the json extension. Don\'t compile PHP with <code>--disable-all/--disable-json</code>.',
            'fatal' => true,
        ],
        'ldap' => [
            'descrip' => 'LDAP Support',
            'error' => 'LDAP support is only required if you want to use an LDAP server for anything like authentication, address books, or preference storage. Compile PHP with <code>--with-ldap</code> to activate the extension.',
        ],
        'mbstring' => [
            'descrip' => 'Mbstring Support',
            'error' => 'If you want to take full advantage of Horde\'s localization features and character set support, you will need the mbstring extension. Compile PHP with <code>--enable-mbstring</code> to activate the extension.',
        ],
        'memcached' => [
            'descrip' => 'Memcached Support (PECL extension)',
            'error' => 'The memcache(d) PECL extension is only needed if you are using a Memcached server for caching or sessions. See horde/doc/INSTALL for information on how to install PECL/PHP extensions.',
            'function' => '_checkMemcache',
        ],
        'mongodb' => [
            'descrip' => 'MongoDB support (PECL extension)',
            'error' => 'If you want to use the MongoDB NoSQL database backend, you must install the mongo(db) extension.',
            'function' => '_checkMongo',
        ],
        'mysql' => [
            'descrip' => 'MySQL Support',
            'error' => 'The MySQL extensions are only required if you want to use a MySQL database server for data storage. See the PHP documentation on how to enable MySQL support when compiling PHP.',
            'function' => '_checkMysql',
        ],
        'openssl' => [
            'descrip' => 'OpenSSL Support',
            'error' => 'The OpenSSL extension is required for various cryptographic actions (highly recommended). Compile PHP with <code>--with-openssl</code> to activate the extension.',
        ],
        'pam' => [
            'descrip' => 'PAM Support (PECL extension)',
            'error' => 'The PAM PECL extension is required to allow PAM authentication to be used.',
            'function' => '_checkPam',
        ],
        'pdo' => [
            'descrip' => 'PDO',
            'error' => 'The PDO extension is required if you plan on using a database backend other than mysql or mysqli with Horde_Db.',
        ],
        'pgsql' => [
            'descrip' => 'PostgreSQL Support',
            'error' => 'The PostgreSQL extension is only required if you want to use a PostgreSQL database server for data storage.',
        ],
        'session' => [
            'descrip' => 'Session Support',
            'error' => 'Session support is required to use Horde. Don\'t compile PHP with <code>--disable-all/--disable-session</code>.',
            'fatal' => true,
        ],
        'SimpleXML' => [
            'descrip' => 'SimpleXML support',
            'error' => 'Horde will not run without the SimpleXML extension. Don\'t compile PHP with <code>--disable-all/--disable-simplexml</code>.',
            'fatal' => true,
        ],
        'tidy' => [
            'descrip' => 'Tidy support',
            'error' => 'The tidy PHP extension is used to sanitize HTML data. Compile PHP with <code>--with-tidy</code> to activate the extension.',
        ],
        'xml' => [
            'descrip' => 'XML Parser support',
            'error' => 'Horde will not run without the xml extension. Don\'t compile PHP with <code>--disable-all/--without-xml</code>.',
            'fatal' => true,
            'function' => '_checkLibxmlVersion',
        ],
        'zlib' => [
            'descrip' => 'Zlib Support',
            'error' => 'The zlib extension is highly recommended for use with Horde.  It allows page compression and handling of ZIP and GZ data. Compile PHP with <code>--with-zlib</code> to activate.',
        ],
    ];

    /**
     * PHP settings list.
     * <pre>
     * KEY:   setting name
     * VALUE: An array with the following entries:
     *        error: (string) Error message.
     *        function: (string) Reference to function to run. If function
     *                  returns non-empty value, error message will be output.
     *        setting: (mixed) Either a boolean (whether setting should be
     *                 on or off) or 'value', which will simply output the
     *                 value of the setting.
     * </pre>
     *
     * @var array
     */
    protected $_settingsList = [
        'allow_url_include' => [
            'setting' => false,
            'error' => 'This is a security hazard. Horde will attempt to disable automatically, but it is best to manually disable also.',
        ],
        'magic_quotes_runtime' => [
            'setting' => false,
            'error' => 'magic_quotes_runtime may cause problems with database inserts, etc. Horde will attempt to disable automatically, but it is best to manually disable also. This setting is deprecated in PHP 5.3.',
        ],
        'magic_quotes_sybase' => [
            'setting' => false,
            'error' => 'magic_quotes_sybase may cause problems with database inserts, etc. Horde will attempt to disable automatically, but it is best to manually disable also. This setting is deprecated in PHP 5.3.',
        ],
        'memory_limit' => [
            'setting' => 'value',
            'error' => 'If PHP\'s internal memory limit is not set high enough Horde will not be able to handle large data items. It is recommended to set the value of memory_limit in php.ini to at least 64M.',
            'function' => '_checkMemoryLimit',
        ],
        'register_globals' => [
            'setting' => false,
            'error' => 'Horde will fatally exit if register_globals is set. Turn it off. This setting is deprecated in PHP 5.3.',
        ],
        'safe_mode' => [
            'setting' => false,
            'error' => 'If safe_mode is enabled, Horde cannot set enviroment variables, which means Horde will be unable to translate the user interface into different languages. This setting is deprecated in PHP 5.3.',
        ],
        'session.auto_start' => [
            'setting' => false,
            'error' => 'Horde won\'t work with automatically started sessions, because it explicitly creates new session when necessary to protect against session fixations.',
        ],
        'session.gc_divisor' => [
            'setting' => 'value',
            'error' => 'PHP automatically garbage collects old session information, as long as this setting (and session.gc_probability) are set to non-zero. It is recommended that this value be "10000" or higher (see doc/INSTALL).',
            'function' => '_checkGcDivisor',
        ],
        'session.gc_probability' => [
            'setting' => 'value',
            'error' => 'PHP automatically garbage collects old session information, as long as this setting (and session.gc_divisor) are set to non-zero. It is recommended that this value be "1". Some distributions may implement the garbage collection externally through a cronjob though.',
            'function' => '_checkGcProbability',
        ],
        'session.use_trans_sid' => [
            'setting' => false,
            'error' => 'Horde will work with session.use_trans_sid turned on, but you may see double session-ids in your URLs, and if the session name in php.ini differs from the session name configured in Horde, you may get two session ids and see other odd behavior. The URL-rewriting that use_trans_sid does also tends to break XHTML compliance. In short, you should really disable this.',
        ],
        'tidy.clean_output' => [
            'setting' => false,
            'error' => 'This will break output of any dynamically created, non-HTML content. Horde will attempt to disable automatically, but it is best to manually disable also.',
        ],
        'zlib.output_compression' => [
            'setting' => false,
            'error' => 'You should not enable output compression unconditionally because some browsers and scripts don\'t work well with output compression. Enable compression in Horde\'s configuration instead, so that we have full control over the conditions where to enable and disable it.',
        ],
    ];

    /**
     * PEAR modules list.
     * <pre>
     * KEY:   PEAR class name
     * VALUE: An array with the following entries:
     *        depends: (?) This module depends on another module.
     *        error: (string) Error message.
     *        function: (string) Reference to function to run if module is
     *                  found.
     *        path: (string) The path to the PEAR module. Only needed if
     *                 KEY is not autoloadable.
     *        required: (boolean) Is this PEAR module required?
     * </pre>
     *
     * @var array
     */
    protected $_pearList = [
        'NetDNS2\\Resolver' => [
            'error' => 'NetDNS2 (mikepultz/netdns2 via Composer) can speed up hostname lookups against broken DNS servers. Install with: composer require mikepultz/netdns2',
        ],
        'Predis\\Client' => [
            'error' => 'The Predis library (predis/predis via Composer) is only needed if you are using a Redis server as a hash table backend for caching or sessions. Install with: composer require predis/predis',
        ],
    ];

    /**
     * Required configuration files.
     * <pre>
     * KEY:   file path
     * VALUE: The error message to use (null to use default message)
     * </pre>
     *
     * @var array
     */
    protected $_fileList = [
        'config/conf.php' => 'You need to login to Horde as an administrator and create the configuration file.',
    ];

    /**
     * Inter-Horde application dependencies.
     * <pre>
     * KEY:   app name
     * VALUE: An array with the following entries:
     *        error: (string) Error message.
     *        version: (string) Minimum version required of the app.
     * </pre>
     *
     * @var array
     */
    protected $_appList = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        /* Store the PHP version information. */
        $this->_phpver = $this->_splitPhpVersion(PHP_VERSION);

        /* We want to be as verbose as possible here. */
        error_reporting(E_ALL);

        /* Set character encoding. */
        header('Content-type: text/html; charset=UTF-8');
        header('Vary: Accept-Language');
    }

    /**
     * Parse PHP version.
     *
     * @param string $version  A PHP-style version string (X.X.X).
     *
     * @param array  The parsed string.
     *               Keys: 'major', 'minor', 'subminor', 'class'
     */
    protected function _splitPhpVersion($version)
    {
        /* First pick off major version, and lower-case the rest. */
        if ((strlen($version) >= 3) && ($version[1] == '.')) {
            $phpver['major'] = substr($version, 0, 3);
            $version = substr(strtolower($version), 3);
        } else {
            $phpver['major'] = $version;
            $phpver['class'] = 'unknown';
            return $phpver;
        }

        if ($version[0] == '.') {
            $version = substr($version, 1);
        }

        /* Next, determine if this is 4.0b or 4.0rc; if so, there is no
           minor, the rest is the subminor, and class is set to beta. */
        $s = strspn($version, '0123456789');
        if ($s == 0) {
            $phpver['subminor'] = $version;
            $phpver['class'] = 'beta';
            return $phpver;
        }

        /* Otherwise, this is non-beta;  the numeric part is the minor,
           the rest is either a classification (dev, cvs) or a subminor
           version (rc<x>, pl<x>). */
        $phpver['minor'] = substr($version, 0, $s);
        if ((strlen($version) > $s)
            && (($version[$s] == '.') || ($version[$s] == '-'))) {
            ++$s;
        }
        $phpver['subminor'] = substr($version, $s);
        if (($phpver['subminor'] == 'cvs')
            || ($phpver['subminor'] == 'dev')
            || (substr($phpver['subminor'], 0, 2) == 'rc')) {
            unset($phpver['subminor']);
            $phpver['class'] = 'dev';
        } else {
            if (!$phpver['subminor']) {
                unset($phpver['subminor']);
            }
            $phpver['class'] = 'release';
        }

        return $phpver;
    }

    /**
     * Check the list of PHP modules.
     *
     * @return string  The HTML output.
     */
    public function phpModuleCheck()
    {
        $output = '';

        foreach ($this->_moduleList as $key => $val) {
            $error_msg = $mod_test = $status_out = $fatal = null;
            $test_function = null;
            $entry = [];

            if (is_array($val)) {
                $descrip = $val['descrip'];
                $fatal = !empty($val['fatal']);
                if (isset($val['phpver'])
                    && (version_compare(PHP_VERSION, $val['phpver']) == -1)) {
                    $mod_test = true;
                    $status_out = 'N/A';
                }
                if (isset($val['error'])) {
                    $error_msg = $val['error'];
                }
                if (isset($val['function'])) {
                    $test_function = $val['function'];
                }
            } else {
                $descrip = $val;
            }

            if (is_null($status_out)) {
                if (is_null($test_function)) {
                    $mod_test = extension_loaded($key);
                } else {
                    $mod_test = call_user_func([$this, $test_function]);
                    if (is_string($mod_test)) {
                        $error_msg = $mod_test;
                        $mod_test = false;
                    }
                }
                $status_out = $this->_status($mod_test, $fatal);
            }

            $entry[] = $descrip;
            $entry[] = $status_out;

            if (!is_null($error_msg) && !$mod_test) {
                $entry[] = $error_msg;
                if (!$fatal) {
                    $entry[] = 1;
                }
            }

            $output .= $this->_outputLine($entry);

            if ($fatal && !$mod_test) {
                echo $output;
                exit;
            }
        }

        return $output;
    }

    /**
     * Check for either horde_lz4 or lzf.
     *
     * @return boolean  False on error.
     */
    protected function _checkLzCompression()
    {
        return extension_loaded('horde_lz4') || extension_loaded('lzf');
    }

    /**
     * Additional check for iconv module implementation.
     *
     * @return boolean  False on error.
     */
    protected function _checkIconvImplementation()
    {
        return extension_loaded('iconv')
               && in_array(ICONV_IMPL, ['libiconv', 'glibc']);
    }

    /**
     * Additional check for libxml version.
     *
     * @return boolean  False on error.
     */
    protected function _checkLibxmlVersion()
    {
        if (!extension_loaded('xml')) {
            return false;
        }
        if (LIBXML_VERSION < 20700) {
            return 'The libxml version is too old. libxml 2.7 or later is required.';
        }
        return true;
    }

    /**
     * Additional check for fileinfo module.
     *
     * @return boolean  False on error.
     */
    protected function _checkFileinfo()
    {
        if (extension_loaded('fileinfo')
            && ($res = @finfo_open())) {
            finfo_close($res);
            return true;
        }

        return false;
    }

    /**
     */
    protected function _checkPam()
    {
        if (extension_loaded('pam')) {
            return true;
        }

        if (extension_loaded('pam_auth')) {
            return 'The PAM extension is required to allow PAM authentication to be used. You have an improper PAM extension loaded. Some installations (e.g. Debian, Ubuntu) ship with an altered version of the PAM extension. You must uninstall this extension and reinstall from PECL.';
        }

        return false;
    }

    /**
     */
    protected function _checkMongo()
    {
        if (extension_loaded('mongodb')) {
            return true;
        }
        if (!extension_loaded('mongo')) {
            return false;
        }
        if (version_compare(phpversion('mongo'), '1.3.0') === -1) {
            return 'The Mongo extension you have installed is too old.';
        }

        return true;
    }

    /**
     */
    protected function _checkMysql()
    {
        return extension_loaded('mysqli') || extension_loaded('pdo_mysql');
    }

    /**
     */
    protected function _checkMemcache()
    {
        return extension_loaded('memcached')
            || extension_loaded('memcache');
    }

    /**
     * Checks the list of PHP settings.
     *
     * @params array $settings  The list of settings to check.
     *
     * @return string  The HTML output.
     */
    public function phpSettingCheck($settings = null)
    {
        $output = '';

        if (is_null($settings)) {
            $settings = $this->_settingsList;
        }

        foreach ($settings as $key => $val) {
            $entry = [];
            if (is_bool($val['setting'])) {
                $result = (ini_get($key) == $val['setting']);
                $entry[] = $key . ' ' . (($val['setting'] === true) ? 'enabled' : 'disabled');
                $entry[] = $this->_status($result);
                if (!$result
                    && (!isset($val['function'])
                     || call_user_func([$this, $val['function']]))) {
                    $entry[] = $val['error'];
                }
            } elseif ($val['setting'] == 'value') {
                $entry[] = $key . ' value';
                $entry[] = ini_get($key);
                if (!empty($val['error'])
                    && (!isset($val['function'])
                     || call_user_func([$this, $val['function']]))) {
                    $entry[] = $val['error'];
                    $entry[] = 1;
                }
            }
            $output .= $this->_outputLine($entry);
        }

        return $output;
    }

    /**
     * Check the list of PHP library modules.
     *
     * @return string  The HTML output.
     */
    public function pearModuleCheck()
    {
        $output = '';

        /* Turn tracking of errors on. */
        unset($php_errormsg);
        ini_set('track_errors', 1);

        /* Print the include_path. */
        $output .= $this->_outputLine(["<strong>PHP Search Path (PHP's include_path)</strong>", '&nbsp;<tt>' . get_include_path() . '</tt>']);

        /* Go through module list. */
        $succeeded = [];
        foreach ($this->_pearList as $key => $val) {
            $entry = [];

            /* If this module depends on another module that we
             * haven't succesfully found, fail the test. */
            if (!empty($val['depends']) && empty($succeeded[$val['depends']])) {
                $result = false;
            } elseif (empty($val['path'])) {
                $result = @class_exists($key);
            } else {
                $result = @include_once $val['path'];
            }
            $error_msg = $val['error'];
            if ($result && isset($val['function'])) {
                $func_output = call_user_func([$this, $val['function']]);
                if ($func_output) {
                    $result = false;
                    $error_msg = $func_output;
                }
            }
            $entry[] = $key;
            $entry[] = $this->_status($result, !empty($val['required']));

            if ($result) {
                $succeeded[$key] = true;
            } else {
                if (!empty($val['required'])) {
                    $error_msg .= ' THIS IS A REQUIRED MODULE!';
                }
                $entry[] = $error_msg;
                if (empty($val['required'])) {
                    $entry[] = 1;
                }
            }

            $output .= $this->_outputLine($entry);
        }

        /* Restore previous value of 'track_errors'. */
        ini_restore('track_errors');

        return $output;
    }

    /**
     * Additional check for 'session.gc_divisor'.
     *
     * @return boolean  Returns true if error string should be displayed.
     */
    protected function _checkMemoryLimit()
    {
        $memlimit = trim(ini_get('memory_limit'));

        // Handle unlimited memory
        if ($memlimit == -1) {
            return false;
        }

        // Extract numeric value and suffix
        $suffix = strtolower(substr($memlimit, -1));
        $value = (int) $memlimit;

        switch ($suffix) {
            case 'g':
                $value *= 1024;
                // Fall-through

                // no break
            case 'm':
                $value *= 1024;
                // Fall-through

                // no break
            case 'k':
                $value *= 1024;
                // Fall-through
        }

        return ($value < 67108864);
    }

    /**
     * Additional check for 'session.gc_divisor'.
     *
     * @return boolean  Returns true if error string should be displayed.
     */
    protected function _checkGcDivisor()
    {
        return (ini_get('session.gc_divisor') < 10000);
    }

    /**
     * Additional check for 'session.gc_probability'.
     *
     * @return boolean  Returns true if error string should be displayed.
     */
    protected function _checkGcProbability()
    {
        return !(ini_get('session.gc_probability')
                 && ini_get('session.gc_divisor'));
    }

    /**
     * Check the list of required files
     *
     * @return string  The HTML output.
     */
    public function requiredFileCheck()
    {
        // Find PHP CLI binary using native PHP
        $php = null;
        $paths = explode(PATH_SEPARATOR, getenv('PATH'));
        foreach ($paths as $path) {
            $phpBinary = $path . DIRECTORY_SEPARATOR . 'php';
            if (is_executable($phpBinary)) {
                $php = $phpBinary;
                break;
            }
        }

        $output = is_null($php)
            ? '<p style="color:orange">Cannot find PHP command-line binary on your system. Syntax checking of configuration files is disabled.</p>'
            : '';

        // Modern deployment structure check
        $output .= $this->_modernDeploymentCheck();

        // Remove conf.php from the old-style check since we handle it in modern check
        $fileList = $this->_fileList;
        unset($fileList['config/conf.php']);
        ksort($fileList);

        return $output . $this->_requiredFileCheck($fileList, $php);
    }

    /**
     * Check that autoloader is working properly
     *
     * @return string  The HTML output.
     */
    public function _autoloaderCheck()
    {
        $output = '';

        // Check web server / SAPI
        $sapi = php_sapi_name();
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
        $serverSignature = $_SERVER['SERVER_SIGNATURE'] ?? '';

        $webServerInfo = '';
        $webServerType = 'unknown';

        if ($sapi === 'cli') {
            $webServerType = 'CLI SAPI';
            $webServerInfo = 'Running under CLI SAPI (command line)';
        } elseif ($sapi === 'cli-server') {
            $webServerType = 'PHP Built-in Server';
            $webServerInfo = 'Running under PHP built-in web server (single-threaded - not for production)';
        } elseif ($sapi === 'fpm-fcgi' || $sapi === 'cgi-fcgi') {
            $webServerType = 'PHP-FPM';
            if (!empty($serverSoftware)) {
                $webServerInfo = "Running under PHP-FPM with <code>{$serverSoftware}</code>";
            } else {
                $webServerInfo = 'Running under PHP-FPM (FastCGI Process Manager)';
            }
        } elseif (stripos($sapi, 'apache') !== false) {
            $webServerType = 'Apache mod_php';
            if (!empty($serverSoftware)) {
                $webServerInfo = "Running under Apache with mod_php (<code>{$serverSoftware}</code>)";
            } else {
                $webServerInfo = 'Running under Apache with mod_php';
            }
        } elseif (!empty($serverSoftware)) {
            // Try to detect from SERVER_SOFTWARE
            if (stripos($serverSoftware, 'apache') !== false) {
                if (stripos($serverSoftware, 'fpm') !== false || stripos($serverSoftware, 'fastcgi') !== false) {
                    $webServerType = 'Apache + PHP-FPM';
                    $webServerInfo = "Running under Apache with PHP-FPM (<code>{$serverSoftware}</code>)";
                } else {
                    $webServerType = 'Apache';
                    $webServerInfo = "Running under Apache (<code>{$serverSoftware}</code>)";
                }
            } elseif (stripos($serverSoftware, 'nginx') !== false) {
                $webServerType = 'nginx + PHP-FPM';
                $webServerInfo = "Running under nginx with PHP-FPM (<code>{$serverSoftware}</code>)";
            } else {
                $webServerType = htmlspecialchars($serverSoftware);
                $webServerInfo = "Web server: <code>{$serverSoftware}</code>";
            }
        } else {
            $webServerInfo = "SAPI: <code>{$sapi}</code>, server software not identifiable";
        }

        // Add warning for single-threaded built-in server
        if ($sapi === 'cli-server') {
            $output .= $this->_outputLine([
                'Web Server',
                $this->_status(true, false),
                $webServerInfo,
                true,  // Orange warning
            ]);
        } elseif ($webServerType === 'unknown') {
            $output .= $this->_outputLine([
                'Web Server',
                $this->_status(true, false),
                $webServerInfo,
                true,  // Orange warning (not identifiable)
            ]);
        } else {
            $output .= $this->_outputLine([
                'Web Server',
                $this->_status(true),
                $webServerInfo,
                'green',
            ]);
        }

        // Check Composer autoloader
        if (!class_exists('Composer\\InstalledVersions')) {
            $output .= $this->_outputLine([
                'Composer Autoloader',
                $this->_status(false),
                'Composer autoloader not detected. Class <code>Composer\\InstalledVersions</code> not found.',
            ]);
        } else {
            $output .= $this->_outputLine([
                'Composer Autoloader',
                $this->_status(true),
                'Composer autoloader is working',
                'green',
            ]);
        }

        // Check PSR-0 autoloading (lib/)
        if (!class_exists('Horde_Test')) {
            $output .= $this->_outputLine([
                'PSR-0 Autoloading (lib/)',
                $this->_status(false),
                'Cannot load <code>Horde_Test</code> - PSR-0 autoloading from lib/ not working',
            ]);
        } else {
            $output .= $this->_outputLine([
                'PSR-0 Autoloading (lib/)',
                $this->_status(true),
                'Successfully loaded <code>Horde_Test</code>',
                'green',
            ]);
        }

        // Check PSR-4 autoloading (src/)
        if (!class_exists('Horde\\Horde\\Service\\AuthenticationService')) {
            $output .= $this->_outputLine([
                'PSR-4 Autoloading (src/)',
                $this->_status(false),
                'Cannot load <code>Horde\\Horde\\Service\\AuthenticationService</code> - PSR-4 autoloading from src/ not working',
            ]);
        } else {
            $output .= $this->_outputLine([
                'PSR-4 Autoloading (src/)',
                $this->_status(true),
                'Successfully loaded <code>Horde\\Horde\\Service\\AuthenticationService</code>',
                'green',
            ]);
        }

        // Check Horde_Core dependency
        if (!class_exists('Horde_Core_Factory_Injector')) {
            $output .= $this->_outputLine([
                'Horde_Core Dependency',
                $this->_status(false),
                'Cannot load <code>Horde_Core_Factory_Injector</code> - Horde_Core not available',
            ]);
        } else {
            $output .= $this->_outputLine([
                'Horde_Core Dependency',
                $this->_status(true),
                'Successfully loaded <code>Horde_Core_Factory_Injector</code>',
                'green',
            ]);
        }

        return $output;
    }

    /**
     * Check critical Horde sub-systems
     *
     * @return string  The HTML output.
     */
    public function _subSystemCheck()
    {
        $output = '';

        // Check Database connectivity
        try {
            $dbConfigured = false;
            $dbType = 'not configured';
            $dbDriver = null;
            $phpExtension = null;
            $extensionLoaded = false;

            // Check if database is configured
            if (defined('HORDE_CONFIG_BASE') || defined('HORDE_BASE')) {
                $conf = $GLOBALS['conf'] ?? null;
                if (isset($conf['sql']['phptype'])) {
                    $dbType = $conf['sql']['phptype'];
                    $dbConfigured = ($dbType !== false && $dbType !== 'false' && $dbType !== '');
                }
            }

            if ($dbConfigured) {
                // Determine expected PHP extension based on phptype
                $extensionMap = [
                    'mysql' => 'mysql',
                    'mysqli' => 'mysqli',
                    'pdo_mysql' => 'pdo_mysql',
                    'pgsql' => 'pgsql',
                    'pdo_pgsql' => 'pdo_pgsql',
                    'sqlite' => 'pdo_sqlite',
                    'pdo_sqlite' => 'pdo_sqlite',
                    'oci8' => 'oci8',
                    'pdo_oci' => 'pdo_oci',
                ];
                $phpExtension = $extensionMap[$dbType] ?? $dbType;
                $extensionLoaded = extension_loaded($phpExtension);

                if (isset($GLOBALS['injector'])) {
                    try {
                        $db = $GLOBALS['injector']->getInstance('Horde_Db_Adapter');
                        if ($db) {
                            $dbDriver = get_class($db);
                            // Try a simple query
                            $db->selectValue('SELECT 1');

                            $details = "Type: <code>{$dbType}</code>, Driver: <code>{$dbDriver}</code>";
                            if ($phpExtension) {
                                $extStatus = $extensionLoaded ? 'loaded' : 'NOT LOADED';
                                $details .= ", PHP extension: <code>{$phpExtension}</code> ({$extStatus})";
                            }

                            $output .= $this->_outputLine([
                                'Database Connection',
                                $this->_status(true),
                                "Connected successfully. {$details}",
                                'green',
                            ]);
                        } else {
                            $output .= $this->_outputLine([
                                'Database Connection',
                                $this->_status(false, false),
                                "Configured as <code>{$dbType}</code> but adapter not initialized. PHP extension <code>{$phpExtension}</code>: " . ($extensionLoaded ? 'loaded' : '<strong>NOT LOADED</strong>'),
                            ]);
                        }
                    } catch (Exception $dbException) {
                        $extInfo = $phpExtension ? " PHP extension <code>{$phpExtension}</code>: " . ($extensionLoaded ? 'loaded' : '<strong>NOT LOADED</strong>') : '';
                        $output .= $this->_outputLine([
                            'Database Connection',
                            $this->_status(false),
                            "Configured as <code>{$dbType}</code> but connection failed: " . htmlspecialchars($dbException->getMessage()) . ".{$extInfo}",
                        ]);
                    }
                } else {
                    $output .= $this->_outputLine([
                        'Database Connection',
                        $this->_status(false, false),
                        "Configured as <code>{$dbType}</code> but injector not available. PHP extension <code>{$phpExtension}</code>: " . ($extensionLoaded ? 'loaded' : '<strong>NOT LOADED</strong>'),
                    ]);
                }
            } else {
                // Database not configured or set to false/none
                $output .= $this->_outputLine([
                    'Database Connection',
                    $this->_status(false, false),
                    "Not configured (conf['sql']['phptype'] = " . htmlspecialchars(var_export($dbType, true)) . ")",
                    true,  // Orange warning
                ]);
            }
        } catch (Exception $e) {
            $output .= $this->_outputLine([
                'Database Connection',
                $this->_status(false),
                'Database check error: ' . htmlspecialchars($e->getMessage()),
            ]);
        }

        // Check Cache system
        try {
            if (isset($GLOBALS['injector'])) {
                $cache = $GLOBALS['injector']->getInstance('Horde_Cache');
                if ($cache) {
                    // Try to set and get a test value
                    $testKey = 'horde_test_' . time();
                    $testValue = 'test_value_' . mt_rand();
                    $cache->set($testKey, $testValue, 60);
                    $retrieved = $cache->get($testKey, 60);

                    if ($retrieved === $testValue) {
                        $cache->expire($testKey);
                        $output .= $this->_outputLine([
                            'Cache System',
                            $this->_status(true),
                            'Cache system is working (driver: ' . htmlspecialchars(get_class($cache)) . ')',
                            'green',
                        ]);
                    } else {
                        $output .= $this->_outputLine([
                            'Cache System',
                            $this->_status(false, false),
                            'Cache set/get test failed - cache may not be persisting values',
                        ]);
                    }
                } else {
                    $output .= $this->_outputLine([
                        'Cache System',
                        $this->_status(false, false),
                        'Cache not initialized',
                    ]);
                }
            } else {
                $output .= $this->_outputLine([
                    'Cache System',
                    $this->_status(false, false),
                    'Injector not available - cannot test cache',
                ]);
            }
        } catch (Exception $e) {
            $output .= $this->_outputLine([
                'Cache System',
                $this->_status(false, false),
                'Cache test failed: ' . htmlspecialchars($e->getMessage()),
            ]);
        }

        // Check Session handler
        try {
            $sessionConfigured = false;
            $configuredType = 'not configured';
            $configuredHashtable = null;
            $actualHandler = null;
            $cookieWarning = '';

            // Check cookie domain configuration
            $conf = $GLOBALS['conf'] ?? null;
            if (isset($conf['cookie']['domain'])) {
                $cookieDomain = $conf['cookie']['domain'];
                $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'unknown';

                // Check if server name has no dots (like 'localhost')
                if (strpos($serverName, '.') === false) {
                    if ($cookieDomain !== '') {
                        $cookieWarning = '<br />Cookie domain is set to <code>' . htmlspecialchars($cookieDomain) . '</code> but server name <code>' . htmlspecialchars($serverName) . '</code> has no dots. Sessions may not work. Set: <code>$conf[\'cookie\'][\'domain\'] = \'\';</code>';
                    }
                }
            }

            // Check session handler configuration
            if (isset($conf['sessionhandler']['type'])) {
                $configuredType = $conf['sessionhandler']['type'];
                $sessionConfigured = true;
                if (isset($conf['sessionhandler']['hashtable'])) {
                    $configuredHashtable = $conf['sessionhandler']['hashtable'];
                }
            }

            if (isset($GLOBALS['session'])) {
                $session = $GLOBALS['session'];
                if ($session && $session->sessionHandler) {
                    $actualHandler = get_class($session->sessionHandler);

                    $details = "Configured: <code>{$configuredType}</code>";
                    if ($configuredHashtable !== null) {
                        $details .= " (hashtable: " . ($configuredHashtable ? 'yes' : 'no') . ")";
                    }
                    $details .= ", Active: <code>{$actualHandler}</code>";

                    // Check if this is Horde_SessionHandler wrapper and introspect the storage backend
                    $storageClass = null;
                    if ($actualHandler === 'Horde_SessionHandler') {
                        try {
                            $reflection = new ReflectionClass($session->sessionHandler);
                            if ($reflection->hasProperty('_storage')) {
                                $storageProperty = $reflection->getProperty('_storage');
                                $storageProperty->setAccessible(true);
                                $storageBackend = $storageProperty->getValue($session->sessionHandler);

                                if ($storageBackend !== null) {
                                    $storageClass = get_class($storageBackend);
                                    $details = "Configured: <code>{$configuredType}</code>";
                                    if ($configuredHashtable !== null) {
                                        $details .= " (hashtable: " . ($configuredHashtable ? 'yes' : 'no') . ")";
                                    }
                                    $details .= ", Active: <code>Horde_SessionHandler</code> wrapping <code>{$storageClass}</code>";
                                }
                            }
                        } catch (ReflectionException $e) {
                            // Reflection failed, just show the wrapper class
                        }
                    }

                    // Check if configuration matches reality
                    // When checking wrapped handlers, use the storage class instead of the wrapper
                    $handlerToCheck = $storageClass ?? $actualHandler;
                    $matches = true;
                    if ($configuredType === 'Builtin' && !preg_match('/Builtin/i', $handlerToCheck)) {
                        $matches = false;
                    } elseif ($configuredType === 'External' && !preg_match('/External/i', $handlerToCheck)) {
                        $matches = false;
                    }

                    if ($cookieWarning) {
                        $output .= $this->_outputLine([
                            'Session Handler',
                            $this->_status(true, false),
                            $details . $cookieWarning,
                            true,  // Orange warning
                        ]);
                    } elseif ($matches) {
                        $output .= $this->_outputLine([
                            'Session Handler',
                            $this->_status(true),
                            $details,
                            'green',
                        ]);
                    } else {
                        $output .= $this->_outputLine([
                            'Session Handler',
                            $this->_status(true, false),
                            "{$details} - Configuration mismatch warning",
                            true,  // Orange warning
                        ]);
                    }
                } else {
                    if ($sessionConfigured) {
                        $output .= $this->_outputLine([
                            'Session Handler',
                            $this->_status(false, false),
                            "Configured: <code>{$configuredType}</code> but session handler not fully initialized (may be in CLI/test mode)" . $cookieWarning,
                            true,  // Orange warning
                        ]);
                    } else {
                        $output .= $this->_outputLine([
                            'Session Handler',
                            $this->_status(false, false),
                            'Session handler not fully initialized and not configured in conf.php' . $cookieWarning,
                            true,  // Orange warning
                        ]);
                    }
                }
            } else {
                if ($sessionConfigured) {
                    $output .= $this->_outputLine([
                        'Session Handler',
                        $this->_status(false, false),
                        "Configured: <code>{$configuredType}</code> but session not available in global scope" . $cookieWarning,
                        true,  // Orange warning
                    ]);
                } else {
                    $output .= $this->_outputLine([
                        'Session Handler',
                        $this->_status(false, false),
                        'Session not available and no sessionhandler configuration found in conf.php' . $cookieWarning,
                        true,  // Orange warning
                    ]);
                }
            }
        } catch (Exception $e) {
            $output .= $this->_outputLine([
                'Session Handler',
                $this->_status(false),
                'Session check error: ' . htmlspecialchars($e->getMessage()),
            ]);
        }

        // Check Logger
        try {
            $conf = $GLOBALS['conf'] ?? null;
            $loggerConfigured = false;
            $logType = 'not configured';
            $logFile = '';
            $logIdent = '';

            if (isset($conf['log'])) {
                $loggerConfigured = $conf['log']['enabled'] ?? false;
                $logType = $conf['log']['type'] ?? 'not configured';
                $logIdent = $conf['log']['ident'] ?? 'HORDE';

                if ($logType === 'file') {
                    $logFile = $conf['log']['name'] ?? '/tmp/horde.log';
                } elseif ($logType === 'stream') {
                    $logFile = $conf['log']['name'] ?? '';
                }
            }

            if (isset($GLOBALS['injector'])) {
                $logger = $GLOBALS['injector']->getInstance('Horde_Log_Logger');
                if ($logger) {
                    $details = "Type: <code>{$logType}</code>";

                    if ($logType === 'file') {
                        $details .= ", File: <code>" . htmlspecialchars($logFile) . "</code>";
                        if (file_exists($logFile)) {
                            if (is_writable($logFile)) {
                                $details .= " (writable)";
                            } else {
                                $details .= " (NOT writable - check permissions)";
                            }
                        } else {
                            $details .= " (does not exist yet)";
                        }
                    } elseif ($logType === 'syslog') {
                        $details .= ", Ident: <code>{$logIdent}</code>";

                        // Check if systemd journal is available
                        $hasJournalctl = false;
                        if (function_exists('shell_exec')) {
                            $which = shell_exec('which journalctl 2>/dev/null');
                            $hasJournalctl = !empty($which);
                        }

                        if ($hasJournalctl) {
                            $journalCmd = "journalctl -t " . escapeshellarg($logIdent) . " -n 50";
                            $details .= "<br />View logs: <code>{$journalCmd}</code>";
                        } else {
                            // Traditional syslog
                            $details .= " (check /var/log/syslog or /var/log/messages)";
                        }
                    } elseif ($logType === 'stream') {
                        $details .= ", Stream: <code>" . htmlspecialchars($logFile) . "</code>";
                    }

                    $output .= $this->_outputLine([
                        'Logger',
                        $this->_status(true),
                        $details,
                        'green',
                    ]);
                } else {
                    $output .= $this->_outputLine([
                        'Logger',
                        $this->_status(false, false),
                        'Logger not initialized',
                    ]);
                }
            } else {
                $output .= $this->_outputLine([
                    'Logger',
                    $this->_status(false, false),
                    'Injector not available - cannot test logger',
                ]);
            }
        } catch (Exception $e) {
            $output .= $this->_outputLine([
                'Logger',
                $this->_status(false, false),
                'Logger test failed: ' . htmlspecialchars($e->getMessage()),
            ]);
        }

        // Check Notification system
        try {
            if (isset($GLOBALS['notification'])) {
                $notification = $GLOBALS['notification'];
                if ($notification) {
                    $output .= $this->_outputLine([
                        'Notification System',
                        $this->_status(true),
                        'Notification system initialized',
                        'green',
                    ]);
                } else {
                    $output .= $this->_outputLine([
                        'Notification System',
                        $this->_status(false, false),
                        'Notification system not initialized',
                    ]);
                }
            } else {
                $output .= $this->_outputLine([
                    'Notification System',
                    $this->_status(false, false),
                    'Notification not available in global scope',
                ]);
            }
        } catch (Exception $e) {
            $output .= $this->_outputLine([
                'Notification System',
                $this->_status(false),
                'Notification error: ' . htmlspecialchars($e->getMessage()),
            ]);
        }

        // Check JWT Authentication
        try {
            $conf = $GLOBALS['conf'] ?? null;
            $jwtConfigured = false;
            $jwtEnabled = false;
            $jwtSecretFile = '';

            if (isset($conf['auth']['jwt'])) {
                $jwtConfigured = true;
                $jwtEnabled = $conf['auth']['jwt']['enabled'] ?? false;
                $jwtSecretFile = $conf['auth']['jwt']['secret_file'] ?? '';
                $jwtIssuer = $conf['auth']['jwt']['issuer'] ?? 'not set';
                $jwtAccessTtl = $conf['auth']['jwt']['access_ttl'] ?? 'not set';

                // Determine secret file path
                if (empty($jwtSecretFile)) {
                    // Default location
                    if (defined('HORDE_CONFIG_BASE')) {
                        $jwtSecretFile = HORDE_CONFIG_BASE . '/horde/jwt.secret';
                    } elseif (defined('HORDE_BASE')) {
                        $jwtSecretFile = HORDE_BASE . '/../../../var/config/horde/jwt.secret';
                    }
                } elseif (!str_starts_with($jwtSecretFile, '/')) {
                    // Relative path
                    if (defined('HORDE_CONFIG_BASE')) {
                        $jwtSecretFile = HORDE_CONFIG_BASE . '/' . $jwtSecretFile;
                    } elseif (defined('HORDE_BASE')) {
                        $jwtSecretFile = HORDE_BASE . '/' . $jwtSecretFile;
                    }
                }

                if ($jwtEnabled) {
                    // Check if secret file exists and is readable
                    if (!empty($jwtSecretFile) && file_exists($jwtSecretFile) && is_readable($jwtSecretFile)) {
                        $secret = trim(file_get_contents($jwtSecretFile));
                        if (!empty($secret) && strlen($secret) >= 32) {
                            $details = "Enabled, Issuer: <code>{$jwtIssuer}</code>, Access TTL: <code>{$jwtAccessTtl}s</code>, Secret file: <code>" . htmlspecialchars($jwtSecretFile) . "</code>";
                            $output .= $this->_outputLine([
                                'JWT Authentication',
                                $this->_status(true),
                                $details,
                                'green',
                            ]);
                        } else {
                            $cmd = "openssl rand -base64 32 > " . escapeshellarg($jwtSecretFile) . " && chmod 600 " . escapeshellarg($jwtSecretFile);
                            $output .= $this->_outputLine([
                                'JWT Authentication',
                                $this->_status(false, false),
                                "Enabled but secret file is empty or too short (< 32 bytes): <code>" . htmlspecialchars($jwtSecretFile) . "</code>. Generate with: <code>{$cmd}</code>",
                                true,  // Orange warning
                            ]);
                        }
                    } elseif (!empty($jwtSecretFile) && !file_exists($jwtSecretFile)) {
                        $cmd = "openssl rand -base64 32 > " . escapeshellarg($jwtSecretFile) . " && chmod 600 " . escapeshellarg($jwtSecretFile);
                        $output .= $this->_outputLine([
                            'JWT Authentication',
                            $this->_status(false, false),
                            "Enabled but secret file not found: <code>" . htmlspecialchars($jwtSecretFile) . "</code>. Generate with: <code>{$cmd}</code>",
                            true,  // Orange warning
                        ]);
                    } elseif (!empty($jwtSecretFile) && !is_readable($jwtSecretFile)) {
                        $cmd = "chmod 600 " . escapeshellarg($jwtSecretFile);
                        $output .= $this->_outputLine([
                            'JWT Authentication',
                            $this->_status(false, false),
                            "Enabled but secret file not readable: <code>" . htmlspecialchars($jwtSecretFile) . "</code>. Fix permissions: <code>{$cmd}</code>",
                            true,  // Orange warning
                        ]);
                    } else {
                        $output .= $this->_outputLine([
                            'JWT Authentication',
                            $this->_status(false, false),
                            'Enabled but secret_file path could not be determined. Set $conf[\'auth\'][\'jwt\'][\'secret_file\'] or define HORDE_CONFIG_BASE.',
                            true,  // Orange warning
                        ]);
                    }
                } elseif ($jwtConfigured) {
                    // Show setup command for when JWT is configured but disabled
                    $defaultFile = '';
                    if (defined('HORDE_CONFIG_BASE')) {
                        $defaultFile = HORDE_CONFIG_BASE . '/horde/jwt.secret';
                    } elseif (defined('HORDE_BASE')) {
                        $defaultFile = HORDE_BASE . '/../../../var/config/horde/jwt.secret';
                    }
                    if (!empty($defaultFile)) {
                        $cmd = "openssl rand -base64 32 > " . escapeshellarg($defaultFile) . " && chmod 600 " . escapeshellarg($defaultFile);
                        $output .= $this->_outputLine([
                            'JWT Authentication',
                            $this->_status(false, false),
                            "Configured but disabled. To enable: Set \$conf['auth']['jwt']['enabled'] = true and generate secret: <code>{$cmd}</code>",
                            true,  // Orange warning
                        ]);
                    } else {
                        $output .= $this->_outputLine([
                            'JWT Authentication',
                            $this->_status(false, false),
                            'Configured but disabled (enabled = false)',
                            true,  // Orange warning
                        ]);
                    }
                } else {
                    $output .= $this->_outputLine([
                        'JWT Authentication',
                        $this->_status(false, false),
                        'Configured but not enabled',
                        true,  // Orange warning
                    ]);
                }
            } else {
                $output .= $this->_outputLine([
                    'JWT Authentication',
                    $this->_status(false, false),
                    'Not configured in conf.php (conf[\'auth\'][\'jwt\'] not found)',
                    true,  // Orange warning
                ]);
            }
        } catch (Exception $e) {
            $output .= $this->_outputLine([
                'JWT Authentication',
                $this->_status(false),
                'JWT check error: ' . htmlspecialchars($e->getMessage()),
            ]);
        }

        // Check Authentication System
        try {
            $conf = $GLOBALS['conf'] ?? null;
            if (isset($conf['auth']['driver'])) {
                $authDriver = $conf['auth']['driver'];
                $authAdmins = $conf['auth']['admins'] ?? [];
                $adminList = is_array($authAdmins) ? implode(', ', $authAdmins) : $authAdmins;

                $details = "Driver: <code>{$authDriver}</code>";
                if (!empty($adminList)) {
                    $details .= ", Admins: <code>" . htmlspecialchars($adminList) . "</code>";
                }

                if (isset($GLOBALS['injector'])) {
                    try {
                        $auth = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Auth')->create();
                        if ($auth) {
                            $authClass = get_class($auth);
                            $details .= ", Active: <code>{$authClass}</code>";

                            // Check if this is the Application wrapper and introspect the wrapped driver
                            if ($authClass === 'Horde_Core_Auth_Application' && method_exists($auth, 'getParam')) {
                                // Try to access the protected $_base property using reflection
                                try {
                                    $reflection = new ReflectionClass($auth);
                                    if ($reflection->hasProperty('_base')) {
                                        $baseProperty = $reflection->getProperty('_base');
                                        $baseProperty->setAccessible(true);
                                        $baseDriver = $baseProperty->getValue($auth);

                                        if ($baseDriver !== null) {
                                            $baseClass = get_class($baseDriver);
                                            $details = "Driver: <code>{$authDriver}</code>";
                                            if (!empty($adminList)) {
                                                $details .= ", Admins: <code>" . htmlspecialchars($adminList) . "</code>";
                                            }
                                            $details .= ", Active: <code>Horde_Core_Auth_Application</code> wrapping <code>{$baseClass}</code>";
                                        }
                                    }
                                } catch (ReflectionException $e) {
                                    // Reflection failed, just show the wrapper class
                                }
                            }

                            $output .= $this->_outputLine([
                                'Authentication System',
                                $this->_status(true),
                                $details,
                                'green',
                            ]);
                        } else {
                            $output .= $this->_outputLine([
                                'Authentication System',
                                $this->_status(false, false),
                                "{$details} - Auth factory returned null",
                                true,  // Orange warning
                            ]);
                        }
                    } catch (Exception $e) {
                        $output .= $this->_outputLine([
                            'Authentication System',
                            $this->_status(false, false),
                            "{$details} - Could not instantiate: " . htmlspecialchars($e->getMessage()),
                            true,  // Orange warning
                        ]);
                    }
                } else {
                    $output .= $this->_outputLine([
                        'Authentication System',
                        $this->_status(true, false),
                        "{$details} (injector not available for full test)",
                        true,  // Orange warning
                    ]);
                }
            } else {
                $output .= $this->_outputLine([
                    'Authentication System',
                    $this->_status(false, false),
                    'Not configured (conf[\'auth\'][\'driver\'] not found)',
                ]);
            }
        } catch (Exception $e) {
            $output .= $this->_outputLine([
                'Authentication System',
                $this->_status(false),
                'Authentication check error: ' . htmlspecialchars($e->getMessage()),
            ]);
        }

        // Check Routes and Observability endpoint
        try {
            $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
            $serverPort = $_SERVER['SERVER_PORT'] ?? 80;
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

            // Try to determine the base URL
            $baseUrl = "{$scheme}://{$serverName}";
            if (($scheme === 'http' && $serverPort != 80) || ($scheme === 'https' && $serverPort != 443)) {
                $baseUrl .= ":{$serverPort}";
            }

            $observabilityUrl = $baseUrl . '/horde/observability/readiness';

            // Check if running under PHP built-in server
            $sapi = php_sapi_name();
            $isBuiltinServer = ($sapi === 'cli-server');

            // Attempt to connect to observability endpoint
            $curlAvailable = function_exists('curl_init');

            if ($curlAvailable) {
                $ch = curl_init($observabilityUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($httpCode === 200) {
                    $output .= $this->_outputLine([
                        'Routes / Observability',
                        $this->_status(true),
                        "Observability endpoint accessible at <code>{$observabilityUrl}</code> (HTTP {$httpCode})",
                        'green',
                    ]);
                } elseif ($httpCode === 401 || $httpCode === 403) {
                    $output .= $this->_outputLine([
                        'Routes / Observability',
                        $this->_status(true, false),
                        "Observability endpoint exists at <code>{$observabilityUrl}</code> but requires authentication (HTTP {$httpCode})",
                        true,  // Orange warning
                    ]);
                } elseif ($httpCode === 404) {
                    $output .= $this->_outputLine([
                        'Routes / Observability',
                        $this->_status(false, false),
                        "Observability endpoint not found at <code>{$observabilityUrl}</code> (HTTP {$httpCode}). Check route configuration.",
                        true,  // Orange warning
                    ]);
                } elseif ($httpCode > 0) {
                    $output .= $this->_outputLine([
                        'Routes / Observability',
                        $this->_status(false, false),
                        "Observability endpoint returned HTTP {$httpCode} at <code>{$observabilityUrl}</code>",
                        true,  // Orange warning
                    ]);
                } else {
                    // Connection failed (timeout or error)
                    if ($isBuiltinServer) {
                        // Expected behavior for PHP built-in server (single-threaded deadlock)
                        $output .= $this->_outputLine([
                            'Routes / Observability',
                            $this->_status(true, false),
                            "Cannot test from within PHP built-in server (single-threaded limitation). Test manually: <a href=\"{$observabilityUrl}\" target=\"_blank\"><code>{$observabilityUrl}</code></a>",
                            true,  // Orange warning (expected limitation)
                        ]);
                    } else {
                        $output .= $this->_outputLine([
                            'Routes / Observability',
                            $this->_status(false, false),
                            "Could not connect to <code>{$observabilityUrl}</code>: " . htmlspecialchars($error ?: 'Connection failed'),
                            true,  // Orange warning
                        ]);
                    }
                }
            } else {
                $output .= $this->_outputLine([
                    'Routes / Observability',
                    $this->_status(false, false),
                    "Cannot test routes - cURL extension not available. Check <code>{$observabilityUrl}</code> manually.",
                    true,  // Orange warning
                ]);
            }
        } catch (Exception $e) {
            $output .= $this->_outputLine([
                'Routes / Observability',
                $this->_status(false),
                'Routes check error: ' . htmlspecialchars($e->getMessage()),
            ]);
        }

        return $output;
    }

    /**
     * Check modern deployment structure (web/ + vendor/ + var/)
     *
     * @return string  The HTML output.
     */
    protected function _modernDeploymentCheck()
    {
        $output = '';

        // Use HORDE_CONFIG_BASE if defined, otherwise derive from HORDE_BASE
        if (defined('HORDE_CONFIG_BASE')) {
            $varConfigDir = HORDE_CONFIG_BASE;
        } elseif (defined('HORDE_BASE')) {
            // Fallback: assume var/config is sibling to vendor
            $hordeBase = realpath(HORDE_BASE);
            $vendorBase = dirname(dirname($hordeBase)); // up from vendor/horde
            $varConfigDir = $vendorBase . '/var/config';
        } else {
            $output .= $this->_outputLine([
                'config/conf.php',
                $this->_status(false, false),
                'Cannot determine configuration paths - HORDE_CONFIG_BASE and HORDE_BASE constants not defined',
            ]);
            return $output;
        }

        $varConfigPath = $varConfigDir . '/horde/conf.php';

        // Determine vendor config path from HORDE_BASE
        if (defined('HORDE_BASE')) {
            $vendorConfigPath = realpath(HORDE_BASE) . '/config/conf.php';
        } else {
            $output .= $this->_outputLine([
                'config/conf.php',
                $this->_status(false, false),
                'Cannot determine vendor config path - HORDE_BASE constant not defined',
            ]);
            return $output;
        }

        $varExists = file_exists($varConfigPath);
        $vendorExists = file_exists($vendorConfigPath);
        $vendorIsSymlink = $vendorExists && is_link($vendorConfigPath);

        // First: Check if primary config exists
        if ($varExists) {
            $output .= $this->_outputLine([
                'Primary conf.php',
                $this->_status(true),
                'Found at <code>' . htmlspecialchars($varConfigPath) . '</code>',
                'green',  // Use green text for positive message
            ]);
        } else {
            $output .= $this->_outputLine([
                'Primary conf.php',
                $this->_status(false),
                'Missing at <code>' . htmlspecialchars($varConfigPath) . '</code>. Please run Horde configuration.',
            ]);
        }

        // Second: Check legacy vendor location
        if (!$vendorExists) {
            if ($varExists) {
                $output .= $this->_outputLine([
                    'Legacy conf.php symlink',
                    $this->_status(false, false),
                    'Missing at <code>vendor/horde/horde/config/conf.php</code>. Create symlink: <code>ln -s ../../../var/config/horde/conf.php vendor/horde/horde/config/conf.php</code>',
                ]);
            } else {
                // Both missing - already reported above
            }
        } elseif ($vendorIsSymlink) {
            $linkTarget = readlink($vendorConfigPath);
            $resolvedTarget = realpath($vendorConfigPath);
            $expectedResolved = $varExists ? realpath($varConfigPath) : null;

            if ($resolvedTarget === $expectedResolved && $varExists) {
                // Symlink points to correct file
                $isRelative = (strpos($linkTarget, '/') !== 0);
                if ($isRelative) {
                    $output .= $this->_outputLine([
                        'Legacy conf.php symlink',
                        $this->_status(true),
                        'At <code>vendor/horde/horde/config/conf.php</code> correctly resolves to primary conf.php (relative symlink)',
                        'green',  // Use green text for positive message
                    ]);
                } else {
                    $output .= $this->_outputLine([
                        'Legacy conf.php symlink',
                        $this->_status(true, false),
                        'At <code>vendor/horde/horde/config/conf.php</code> correctly resolves to primary conf.php but uses absolute path <code>' . htmlspecialchars($linkTarget) . '</code>. Consider relative: <code>ln -sf ../../../var/config/horde/conf.php vendor/horde/horde/config/conf.php</code>',
                        true,  // Orange warning
                    ]);
                }
            } else {
                $output .= $this->_outputLine([
                    'Legacy conf.php symlink',
                    $this->_status(false),
                    'At <code>vendor/horde/horde/config/conf.php</code> points to wrong location: <code>' . htmlspecialchars($linkTarget) . '</code>. Fix: <code>ln -sf ../../../var/config/horde/conf.php vendor/horde/horde/config/conf.php</code>',
                ]);
            }
        } else {
            // vendor is a regular file
            if ($varExists) {
                $varContent = file_get_contents($varConfigPath);
                $vendorContent = file_get_contents($vendorConfigPath);

                if ($varContent === $vendorContent) {
                    $output .= $this->_outputLine([
                        'Legacy conf.php (file)',
                        $this->_status(true, false),
                        'At <code>vendor/horde/horde/config/conf.php</code> is a regular file (copy or hard link) with identical content to primary. OK - but consider symlink to avoid sync issues: <code>ln -sf ../../../var/config/horde/conf.php vendor/horde/horde/config/conf.php</code>',
                        true,  // Orange suggestion
                    ]);
                } else {
                    $output .= $this->_outputLine([
                        'Legacy conf.php (file)',
                        $this->_status(false),
                        '<strong>CONFLICT:</strong> At <code>vendor/horde/horde/config/conf.php</code> is a regular file with DIFFERENT content than primary! This will cause inconsistent behavior. Replace with symlink: <code>rm vendor/horde/horde/config/conf.php && ln -s ../../../var/config/horde/conf.php vendor/horde/horde/config/conf.php</code>',
                    ]);
                }
            } else {
                $output .= $this->_outputLine([
                    'Legacy conf.php (file)',
                    $this->_status(false, false),
                    'At <code>vendor/horde/horde/config/conf.php</code> exists but primary is missing. Move to primary location: <code>mkdir -p ' . htmlspecialchars(dirname($varConfigPath)) . ' && mv vendor/horde/horde/config/conf.php ' . htmlspecialchars($varConfigPath) . ' && ln -s ../../../var/config/horde/conf.php vendor/horde/horde/config/conf.php</code>',
                    true,  // Orange warning
                ]);
            }
        }

        return $output;
    }

    /**
     * Check the list of required files
     *
     * @param array $filelist    List of files to check.
     * @param string $php        PHP CLI location.
     * @param boolean $is_local  Is filelist a "local" file?
     *
     * @return string  The HTML output.
     */
    protected function _requiredFileCheck($filelist, $php, $is_local = false)
    {
        $filedir = $GLOBALS['registry']->get('fileroot');
        $output = $tmp = '';

        foreach ($filelist as $key => $val) {
            $entry = [$key];
            $file = $filedir . '/' . $key;
            $entry2 = null;

            if (file_exists($file)) {
                if (is_readable($file)) {
                    if (is_null($php)) {
                        $entry[] = $this->_status(true);
                        $check_local = true;
                    } else {
                        exec(escapeshellcmd($php) . ' -l ' . escapeshellarg($file), $tmp, $error);
                        if ($error === 255) {
                            $entry[] = $this->_status(false);
                            $entry[] = 'The file <code>' . htmlspecialchars($key) . '</code> has PHP syntax errors:' . "\n<pre>" . htmlspecialchars(trim(implode("\n", $tmp))) . '</pre>';
                        } else {
                            ob_start();
                            include $file;
                            $parse_contents = trim(ob_get_clean());

                            if (strlen($parse_contents)) {
                                $entry[] = $this->_status(false);
                                $contents = file_get_contents($file);
                                if (preg_match("/<?php\s+/", $contents)) {
                                    $entry[] = 'The file <code>' . htmlspecialchars($key) . '</code> is outputting a non-empty string when parsed. Configuration files should not output anything. Output string:' . "\n<pre>" . htmlspecialchars($parse_contents) . '</pre>';
                                } else {
                                    $entry[] = 'The file <code>' . htmlspecialchars($key) . '</code> appears to be missing the \'&lt;?php\' opening tag.';
                                }
                            } else {
                                $entry[] = $this->_status(true);
                                $check_local = true;
                            }
                        }
                    }

                    if ($check_local && !$is_local) {
                        $local_file = preg_replace("/\.php$/", '.local.php', $key);
                        if (file_exists($filedir . '/' . $local_file)) {
                            $entry2 = $this->_requiredFileCheck([
                                $local_file => null,
                            ], $php, true);
                        }
                    }
                } else {
                    $entry[] = $this->_status(false);
                    $entry[] = 'The file <code>' . htmlspecialchars($key) . '</code> is not readable by the web user.';
                }
            } else {
                $entry[] = $this->_status(false);
                $entry[] = empty($val)
                    ? 'The file <code>' . htmlspecialchars($key) . '</code> appears to be missing.'
                    : $val;
            }

            $output .= $this->_outputLine($entry);
            if (!is_null($entry2)) {
                $output .= $entry2;
            }
        }

        return $output;
    }

    /**
     * Check the list of required Horde applications.
     *
     * @return string  The HTML output.
     */
    public function requiredAppCheck()
    {
        $output = '';

        $horde_apps = $GLOBALS['registry']->listApps(null, true, null);

        foreach ($this->_appList as $key => $val) {
            $entry = [];
            $entry[] = $key;

            if (!isset($horde_apps[$key])) {
                $entry[] = $this->_status(false, false);
                $entry[] = $val['error'];
                $entry[] = 1;
            } else {
                /* Strip '-git', and H# (ver) from version string. */
                $origver = $GLOBALS['registry']->getVersion($key);
                $appver = preg_replace('/(H\d) \((.*)\)/', '$2', str_replace('-git', '', $origver));
                if (version_compare($val['version'], $appver) === 1) {
                    $entry[] = $this->_status(false, false) . ' (Have version: ' . $origver . '; Need version: ' . $val['version'] . ')';
                    $entry[] = $val['error'];
                    $entry[] = 1;
                } else {
                    $entry[] = $this->_status(true) . ' (Version: ' . $origver . ')';
                }
            }
            $output .= $this->_outputLine($entry);
        }

        return $output;
    }

    /**
     * Obtain information on the PHP version.
     *
     * @return object stdClass  TODO
     */
    public function getPhpVersionInformation()
    {
        $output = new stdClass();
        $vers_check = true;

        $testscript = Horde::selfUrl(true);
        $output->phpinfo = $testscript->copy()->add('mode', 'phpinfo');
        $output->extensions = $testscript->copy()->add('mode', 'extensions');
        $output->version = PHP_VERSION;
        $output->major = $this->_phpver['major'];
        if (isset($this->_phpver['minor'])) {
            $output->minor = $this->_phpver['minor'];
        }
        if (isset($this->_phpver['subminor'])) {
            $output->subminor = $this->_phpver['subminor'];
        }
        $output->class = $this->_phpver['class'];

        $output->status_color = 'red';
        if ($output->major < '5.3') {
            $output->status = 'This version of PHP is not supported. You need to upgrade to a more recent version.';
            $vers_check = false;
        } elseif ($output->major == '5.3') {
            $output->status = 'You are using an old, deprecated version of PHP. It is highly recommended that you upgrade to at least PHP 5.4 for performance, stability, and security reasons.';
            $output->status_color = 'orange';
        } elseif (in_array($output->major, $this->_supported)) {
            $output->status = 'You are running a supported version of PHP.';
            $output->status_color = 'green';
        } else {
            $output->status = 'This version of PHP has not been fully tested with this version of Horde.';
            $output->status_color = 'orange';
        }

        if (!$vers_check) {
            $output->version_check = 'Horde requires PHP 5.3.0 or greater.';
        }

        return $output;
    }

    /**
     * Output the results of a status check.
     *
     * @param boolean $bool      The result of the status check.
     * @param boolean $required  Whether the checked item is required.
     *
     * @return string  The HTML of the result of the status check.
     */
    protected function _status($bool, $required = true)
    {
        if ($bool) {
            return '<strong style="color:green">Yes</strong>';
        } elseif ($required) {
            return '<strong style="color:red">No</strong>';
        }

        return '<strong style="color:orange">No</strong>';
    }

    /**
     * Internal output function.
     *
     * @param array $entry  Array with the following values:
     * <pre>
     * 1st value: Header
     * 2nd value: Test Result
     * 3rd value: Error message (if present)
     * 4th value: Error level (if present): 0 = error, 1 = warning
     * </pre>
     *
     * @return string  HTML output.
     */
    protected function _outputLine($entry)
    {
        $output = '<li>' . array_shift($entry) . ': ' . array_shift($entry);
        if (!empty($entry)) {
            $msg = array_shift($entry);
            $color = 'red';  // Default to red for errors

            if (!empty($entry)) {
                $colorParam = array_shift($entry);
                if ($colorParam === 'green') {
                    $color = 'green';
                } elseif ($colorParam === true || $colorParam === 'orange') {
                    $color = 'orange';
                }
            }

            $output .= '<br /><strong style="color:' . $color . '">' . $msg . "</strong>\n";
        }

        return $output . "</li>\n";
    }

    /**
     * Any application specific tests that need to be done.
     *
     * @return string  HTML output.
     */
    public function appTests()
    {
        /* File upload information. */
        $upload_check = $this->phpSettingCheck([
            'file_uploads' => [
                'error' => 'file_uploads must be enabled for some features like sending emails with IMP.',
                'setting' => true,
            ],
        ]);
        $upload_tmp_dir = ($dir = ini_get('upload_tmp_dir'))
            ? '<li>upload_tmp_dir: <strong style="color:"' . (is_writable($dir) ? 'green' : 'red') . '">' . $dir . '</strong></li>'
            : '';

        $ret = '<h1>File Uploads</h1><ul>'
            . $upload_check
            . $upload_tmp_dir
            . '<li>upload_max_filesize: ' . ini_get('upload_max_filesize') . '</li>'
            . '<li>post_max_size: ' . ini_get('post_max_size') . '<br />'
            . 'This value should be several times the expect largest upload size (notwithstanding any upload limits present in an application). Any upload that exceeds this size will cause any state information sent along with the uploaded data to be lost. This is a PHP limitation and can not be worked around.'
            . '</li></ul>';

        /* Check for supported translations. */
        $ret .= '<h1>Supported locales</h1><ul>';
        $missing = false;
        $supportedCount = 0;
        foreach ($GLOBALS['registry']->nlsconfig->languages as $code => $language) {
            if ($GLOBALS['registry']->nlsconfig->validLang($code)) {
                $color = 'green';
                $supportedCount++;
            } else {
                $color = 'red';
                $missing = true;
            }
            $ret .= sprintf(
                '<li>%s &#x202d;(%s): <strong style="color:%s">%s</strong></li>',
                $language,
                $code,
                $color,
                $color == 'green' ? 'Yes' : 'No'
            );
        }
        $ret .= '</ul>';

        // If no locales are supported, provide installation instructions
        if ($supportedCount === 0) {
            $ret .= '<div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 10px 0; border-radius: 4px;">';
            $ret .= '<strong style="color: #856404;">⚠️ No locales are currently supported on this system</strong><br /><br />';
            $ret .= 'To install and generate locales, run the following commands:<br /><br />';
            $ret .= '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">';
            $ret .= '# Install locale packages (Debian/Ubuntu)
sudo apt-get update
sudo apt-get install -y locales locales-all

# Generate common locales
sudo locale-gen en_US.UTF-8
sudo locale-gen de_DE.UTF-8
sudo locale-gen fr_FR.UTF-8
sudo locale-gen es_ES.UTF-8
sudo locale-gen it_IT.UTF-8
sudo locale-gen nl_NL.UTF-8
sudo locale-gen pt_BR.UTF-8
sudo locale-gen ja_JP.UTF-8
sudo locale-gen zh_CN.UTF-8

# Update locale cache
sudo update-locale

# Verify locales are installed
locale -a</pre>';
            $ret .= '<strong style="color: #856404;">⚠️ After installing locales, restart your web server:</strong><br /><br />';
            $ret .= '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">';
            $ret .= '# For Apache
sudo systemctl restart apache2

# For PHP Built-in Server
# Stop current server (Ctrl+C) and restart it

# For PHP-FPM
sudo systemctl restart php8.2-fpm  # Adjust version as needed</pre>';
            $ret .= '</div>';
        }

        /* Determine if 'static' is writable by the web user. */
        $user = function_exists('posix_getuid') ? posix_getpwuid(posix_getuid()) : null;
        $static_dir = $GLOBALS['registry']->get('staticfs', 'horde');

        $ret .= '<h1>Local File Permissions</h1><ul>'
            . sprintf(
                '<li>Is <tt>%s</tt> writable by the web server user%s? ',
                htmlspecialchars($static_dir),
                $user ? (' (' . $user['name'] . ')') : ''
            );
        $ret .= is_writable($static_dir)
            ? '<strong style="color:green">Yes</strong>'
            : '<strong style="color:red">No</strong><br /><strong style="color:orange">If caching javascript and CSS files by storing them in static files (HIGHLY RECOMMENDED), this directory must be writable as the user the web server runs as%s.</strong>';

        /* Determine if 'tmpdir' is writable by the web user. */
        $tmpdir = Horde::getTempDir();
        $ret .= sprintf(
            '<li>Is tmpdir <tt>%s</tt> writable by the web server user%s? ',
            htmlspecialchars($tmpdir),
            $user ? (' (' . $user['name'] . ')') : ''
        );
        $ret .= is_writable($tmpdir)
            ? '<strong style="color:green">Yes</strong>'
            : '<strong style="color:red">No</strong><br />';

        if (extension_loaded('imagick')) {
            $im = new Imagick();
            $imagick = is_callable([$im, 'getIteratorIndex']);
            $ret .= '</li></ul><h1>Imagick</h1><ul>'
                . '<li>Imagick compiled against current ImageMagick version: <strong style="color:' . ($imagick ? 'green">Yes' : 'red">No') . '</strong>';
        }

        return $ret . '</li></ul>';
    }

}
