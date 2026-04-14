<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 */

namespace Horde\Horde\Service\Health;

use Horde_Db_Adapter;
use Horde_Cache;
use Horde_Log_Logger;
use Horde_Session;
use Horde\Injector\Injector;
use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * Health check service for Horde subsystems
 *
 * Provides diagnostic checks for database, cache, session, logger, and JWT.
 * Extracted from legacy test.php functionality.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 */
class HealthCheckService
{
    public function __construct(
        private Injector $injector
    ) {}

    /**
     * Check database connectivity
     *
     * @return array Health check result
     */
    public function checkDatabase(): array
    {
        try {
            $conf = $GLOBALS['conf'] ?? [];
            $dbType = $conf['sql']['phptype'] ?? null;

            if (!$dbType || $dbType === false || $dbType === 'false' || $dbType === '') {
                return [
                    'status' => 'warning',
                    'message' => 'Database not configured',
                    'details' => [
                        'configured' => false,
                    ],
                ];
            }

            // Check PHP extension
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

            try {
                $db = $this->injector->getInstance(Horde_Db_Adapter::class);
                if (!$db) {
                    return [
                        'status' => 'error',
                        'message' => 'Database adapter not initialized',
                        'details' => [
                            'configured' => true,
                            'type' => $dbType,
                            'extension' => $phpExtension,
                            'extension_loaded' => $extensionLoaded,
                        ],
                    ];
                }

                // Try simple query
                $db->selectValue('SELECT 1');

                return [
                    'status' => 'ok',
                    'message' => 'Database connection successful',
                    'details' => [
                        'configured' => true,
                        'type' => $dbType,
                        'driver' => get_class($db),
                        'extension' => $phpExtension,
                        'extension_loaded' => $extensionLoaded,
                    ],
                ];
            } catch (Exception $e) {
                return [
                    'status' => 'error',
                    'message' => 'Database connection failed: ' . $e->getMessage(),
                    'details' => [
                        'configured' => true,
                        'type' => $dbType,
                        'extension' => $phpExtension,
                        'extension_loaded' => $extensionLoaded,
                        'error' => $e->getMessage(),
                    ],
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database check error: ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check cache system
     *
     * @return array Health check result
     */
    public function checkCache(): array
    {
        try {
            $cache = $this->injector->getInstance(Horde_Cache::class);
            if (!$cache) {
                return [
                    'status' => 'error',
                    'message' => 'Cache not initialized',
                    'details' => [],
                ];
            }

            // Test set/get
            $testKey = 'horde_health_check_' . time();
            $testValue = 'test_value_' . mt_rand();
            $cache->set($testKey, $testValue, 60);
            $retrieved = $cache->get($testKey, 60);

            if ($retrieved === $testValue) {
                $cache->expire($testKey);
                return [
                    'status' => 'ok',
                    'message' => 'Cache system is working',
                    'details' => [
                        'driver' => get_class($cache),
                    ],
                ];
            }

            return [
                'status' => 'warning',
                'message' => 'Cache set/get test failed - cache may not be persisting values',
                'details' => [
                    'driver' => get_class($cache),
                ],
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache test failed: ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check session handler
     *
     * @return array Health check result
     */
    public function checkSession(): array
    {
        try {
            $conf = $GLOBALS['conf'] ?? [];
            $session = $GLOBALS['session'] ?? null;

            $configuredType = $conf['sessionhandler']['type'] ?? 'not configured';
            $configuredHashtable = $conf['sessionhandler']['hashtable'] ?? null;

            if (!$session || !$session->sessionHandler) {
                return [
                    'status' => 'warning',
                    'message' => 'Session handler not fully initialized (may be CLI mode)',
                    'details' => [
                        'configured_type' => $configuredType,
                        'configured_hashtable' => $configuredHashtable,
                    ],
                ];
            }

            $actualHandler = get_class($session->sessionHandler);
            $details = [
                'configured_type' => $configuredType,
                'configured_hashtable' => $configuredHashtable,
                'actual_handler' => $actualHandler,
            ];

            // Check for cookie domain issues
            $cookieDomain = $conf['cookie']['domain'] ?? null;
            $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'unknown';

            if ($cookieDomain !== null && $cookieDomain !== '' && strpos($serverName, '.') === false) {
                return [
                    'status' => 'warning',
                    'message' => 'Cookie domain set but server name has no dots - sessions may not work',
                    'details' => array_merge($details, [
                        'cookie_domain' => $cookieDomain,
                        'server_name' => $serverName,
                        'warning' => 'Set $conf[\'cookie\'][\'domain\'] = \'\'; for local development',
                    ]),
                ];
            }

            // Introspect storage backend if Horde_SessionHandler wrapper
            if ($actualHandler === 'Horde_SessionHandler') {
                try {
                    $reflection = new ReflectionClass($session->sessionHandler);
                    if ($reflection->hasProperty('_storage')) {
                        $storageProperty = $reflection->getProperty('_storage');
                        $storageProperty->setAccessible(true);
                        $storageBackend = $storageProperty->getValue($session->sessionHandler);

                        if ($storageBackend !== null) {
                            $details['storage_backend'] = get_class($storageBackend);
                        }
                    }
                } catch (ReflectionException $e) {
                    // Reflection failed, skip storage backend details
                }
            }

            return [
                'status' => 'ok',
                'message' => 'Session handler is working',
                'details' => $details,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Session check error: ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check logger
     *
     * @return array Health check result
     */
    public function checkLogger(): array
    {
        try {
            $conf = $GLOBALS['conf'] ?? [];
            $logType = $conf['log']['type'] ?? 'not configured';
            $logEnabled = $conf['log']['enabled'] ?? false;

            if (!$logEnabled) {
                return [
                    'status' => 'warning',
                    'message' => 'Logger not enabled in configuration',
                    'details' => [
                        'enabled' => false,
                        'type' => $logType,
                    ],
                ];
            }

            try {
                $logger = $this->injector->getInstance(Horde_Log_Logger::class);
                if (!$logger) {
                    return [
                        'status' => 'error',
                        'message' => 'Logger not initialized',
                        'details' => [
                            'enabled' => $logEnabled,
                            'type' => $logType,
                        ],
                    ];
                }

                $details = [
                    'enabled' => $logEnabled,
                    'type' => $logType,
                ];

                if ($logType === 'file') {
                    $logFile = $conf['log']['name'] ?? '/tmp/horde.log';
                    $details['file'] = $logFile;
                    $details['file_exists'] = file_exists($logFile);
                    $details['file_writable'] = file_exists($logFile) && is_writable($logFile);
                } elseif ($logType === 'syslog') {
                    $details['ident'] = $conf['log']['ident'] ?? 'HORDE';
                } elseif ($logType === 'stream') {
                    $details['stream'] = $conf['log']['name'] ?? '';
                }

                return [
                    'status' => 'ok',
                    'message' => 'Logger is configured and initialized',
                    'details' => $details,
                ];
            } catch (Exception $e) {
                return [
                    'status' => 'error',
                    'message' => 'Logger initialization failed: ' . $e->getMessage(),
                    'details' => [
                        'enabled' => $logEnabled,
                        'type' => $logType,
                        'error' => $e->getMessage(),
                    ],
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Logger check error: ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check JWT authentication
     *
     * @return array Health check result
     */
    public function checkJwt(): array
    {
        try {
            $conf = $GLOBALS['conf'] ?? [];
            $jwtEnabled = $conf['auth']['jwt']['enabled'] ?? false;

            if (!$jwtEnabled) {
                return [
                    'status' => 'warning',
                    'message' => 'JWT authentication not enabled',
                    'details' => [
                        'enabled' => false,
                    ],
                ];
            }

            $jwtSecretFile = $conf['auth']['jwt']['secret_file'] ?? '';
            $jwtIssuer = $conf['auth']['jwt']['issuer'] ?? 'not set';
            $jwtAccessTtl = $conf['auth']['jwt']['access_ttl'] ?? 'not set';

            // Determine secret file path
            if (empty($jwtSecretFile)) {
                if (defined('HORDE_CONFIG_BASE')) {
                    $jwtSecretFile = HORDE_CONFIG_BASE . '/horde/jwt.secret';
                } elseif (defined('HORDE_BASE')) {
                    $jwtSecretFile = HORDE_BASE . '/../../../var/config/horde/jwt.secret';
                }
            } elseif (!str_starts_with($jwtSecretFile, '/')) {
                if (defined('HORDE_CONFIG_BASE')) {
                    $jwtSecretFile = HORDE_CONFIG_BASE . '/' . $jwtSecretFile;
                } elseif (defined('HORDE_BASE')) {
                    $jwtSecretFile = HORDE_BASE . '/' . $jwtSecretFile;
                }
            }

            if (!file_exists($jwtSecretFile)) {
                return [
                    'status' => 'error',
                    'message' => 'JWT secret file not found',
                    'details' => [
                        'enabled' => true,
                        'secret_file' => $jwtSecretFile,
                        'issuer' => $jwtIssuer,
                        'access_ttl' => $jwtAccessTtl,
                        'error' => 'Secret file does not exist',
                    ],
                ];
            }

            if (!is_readable($jwtSecretFile)) {
                return [
                    'status' => 'error',
                    'message' => 'JWT secret file not readable',
                    'details' => [
                        'enabled' => true,
                        'secret_file' => $jwtSecretFile,
                        'issuer' => $jwtIssuer,
                        'access_ttl' => $jwtAccessTtl,
                        'error' => 'Secret file exists but is not readable',
                    ],
                ];
            }

            $secret = trim(file_get_contents($jwtSecretFile));
            if (empty($secret) || strlen($secret) < 32) {
                return [
                    'status' => 'error',
                    'message' => 'JWT secret file is empty or too short (< 32 bytes)',
                    'details' => [
                        'enabled' => true,
                        'secret_file' => $jwtSecretFile,
                        'issuer' => $jwtIssuer,
                        'access_ttl' => $jwtAccessTtl,
                        'error' => 'Secret is too short, should be at least 32 bytes',
                    ],
                ];
            }

            return [
                'status' => 'ok',
                'message' => 'JWT authentication is properly configured',
                'details' => [
                    'enabled' => true,
                    'secret_file' => $jwtSecretFile,
                    'issuer' => $jwtIssuer,
                    'access_ttl' => $jwtAccessTtl,
                    'secret_length' => strlen($secret),
                ],
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'JWT check error: ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check authentication backend
     *
     * @return array Health check result
     */
    public function checkAuth(): array
    {
        try {
            $conf = $GLOBALS['conf'] ?? [];
            $authDriver = $conf['auth']['driver'] ?? 'not configured';

            try {
                // Try modern AuthService first
                try {
                    $authService = $this->injector->getInstance(\Horde\Core\Auth\AuthService::class);
                    $auth = $authService->getBackend();
                } catch (Exception $e) {
                    // Fall back to legacy 'Horde_Auth' string binding
                    $auth = $this->injector->getInstance('Horde_Auth');
                }

                if (!$auth) {
                    return [
                        'status' => 'error',
                        'message' => 'Authentication backend not initialized',
                        'details' => [
                            'configured_driver' => $authDriver,
                        ],
                    ];
                }

                $actualClass = get_class($auth);
                $details = [
                    'configured_driver' => $authDriver,
                    'actual_class' => $actualClass,
                ];

                // Introspect wrapped driver if Horde_Core_Auth_Application
                if ($actualClass === 'Horde_Core_Auth_Application') {
                    try {
                        $reflection = new ReflectionClass($auth);
                        if ($reflection->hasProperty('_base')) {
                            $baseProperty = $reflection->getProperty('_base');
                            $baseProperty->setAccessible(true);
                            $baseDriver = $baseProperty->getValue($auth);

                            if ($baseDriver !== null) {
                                $details['wrapped_driver'] = get_class($baseDriver);
                            }
                        }
                    } catch (ReflectionException $e) {
                        // Reflection failed, skip wrapped driver details
                    }
                }

                return [
                    'status' => 'ok',
                    'message' => 'Authentication backend is initialized',
                    'details' => $details,
                ];
            } catch (Exception $e) {
                return [
                    'status' => 'error',
                    'message' => 'Authentication backend initialization failed: ' . $e->getMessage(),
                    'details' => [
                        'configured_driver' => $authDriver,
                        'error' => $e->getMessage(),
                    ],
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Authentication check error: ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Run all health checks
     *
     * @return array Array of all health check results
     */
    public function checkAll(): array
    {
        return [
            'db' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'session' => $this->checkSession(),
            'logger' => $this->checkLogger(),
            'auth' => $this->checkAuth(),
            'jwt' => $this->checkJwt(),
        ];
    }
}
