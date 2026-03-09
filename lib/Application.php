<?php

/**
 * Horde application API.
 *
 * This file defines Horde's core API interface. Other core Horde libraries
 * can interact with Horde through this API.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl LGPL-2
 * @package   Horde
 */

/* Load the Horde Framework core (needed to autoload
 * Horde_Registry_Application::). */
if (!defined('HORDE_CORE_LOADED')) {
    require_once __DIR__ . '/core.php';
}
use Horde\Backup;

if (!class_exists('Horde_Application')) {
    class Horde_Application extends Horde_Registry_Application
    {
        /**
         */
        public $version = '6.0.0-beta4';

        /**
         */
        public $features = [
            'smartmobileView' => true,
        ];

        /**
         */
        public function logout()
        {
            // Destroy any session-only temp files (since Horde_Core 1.7.0).
            foreach ($GLOBALS['session']->get('horde', 'gc_tempfiles', Horde_Session::TYPE_ARRAY) as $file) {
                @unlink($file);
            }
        }

        /**
         */
        public function perms()
        {
            $permissions = [
                'max_blocks' => [
                    'title' => _("Maximum Number of Portal Blocks"),
                    'type' => 'int',
                ],
                'administration' => [
                    'title' => _("Administration"),
                ],
            ];

            if (!empty($GLOBALS['conf']['activesync']['enabled'])) {
                $this->_addActiveSyncPerms($permissions);
            }

            try {
                foreach ($GLOBALS['registry']->callByPackage('horde', 'admin_list') as $perm_key => $perm_details) {
                    $permissions['administration:' . $perm_key] = ['title' => Horde::stripAccessKey($perm_details['name'])];
                }
            } catch (Horde_Exception $e) {/*what to do if this fails?*/
            }

            return $permissions;
        }

        /**
         */
        public function hasPermission($permission, $allowed, $opts = [])
        {
            if (is_array($allowed)) {
                switch ($permission) {
                    case 'max_blocks':
                        $allowed = max($allowed);
                        break;
                }
            }
            return $allowed;
        }

        /**
         */
        public function configSpecialValues($what)
        {
            switch ($what) {
                case 'apps':
                    $apps = Horde_Array::valuesToKeys($GLOBALS['registry']->listApps(['active']));
                    asort($apps);
                    return $apps;

                case 'languages':
                    $convert_numeric = function ($num) {
                        return Horde_String::convertCharset(
                            pack('H*', $num[1]),
                            'ucs-2',
                            'UTF-8'
                        );
                    };
                    $convert_symbolic = function ($symbol) {
                        return Horde_String::convertCharset(
                            html_entity_decode($symbol[1], ENT_COMPAT, 'iso-8859-1'),
                            'iso-8859-1',
                            'UTF-8'
                        );
                    };
                    return array_map(
                        function ($val) {
                            return preg_replace_callback(
                                ['/&#x([0-9a-f]{4});/i',
                                    '/(&[^;]+;)/'],
                                [$convert_numeric,
                                    $convert_symbolic],
                                $val
                            );
                        },
                        $GLOBALS['registry']->nlsconfig->languages
                    );

                case 'blocks':
                    return $GLOBALS['injector']->getInstance('Horde_Core_Factory_BlockCollection')->create()->getBlocksList();

                case 'mapsources':
                    return [
                        'Google' => 'Google',
                        'Bing' => 'Bing',
                        'Osm' => 'OpenStreetMap',
                        'Ocm' => 'OpenCycleMap',
                        'Mapquest' => 'OpenMapquest',
                    ];

                case 'geocoders':
                    return [
                        'None' => null,
                        'Google' => 'Google',
                    ];
            }
        }

        /**
         */
        public function removeUserData($user)
        {
            $error = false;

            /* Remove user from all groups */
            $groups = $GLOBALS['injector']->getInstance('Horde_Group');
            if (!$groups->readOnly()) {
                try {
                    $allGroups = $groups->listGroups($user);
                    foreach (array_keys($allGroups) as $id) {
                        $groups->removeUser($id, $user);
                    }
                } catch (Horde_Group_Exception $e) {
                    Horde::log($e, 'NOTICE');
                    $error = true;
                }
            }

            /* Remove the user from all application permissions */
            $perms = $GLOBALS['injector']->getInstance('Horde_Perms');
            try {
                $tree = $perms->getTree();
            } catch (Horde_Perms_Exception $e) {
                Horde::log($e, 'NOTICE');
                $error = true;
                $tree = [];
            }

            foreach (array_keys($tree) as $id) {
                try {
                    $perm = $perms->getPermissionById($id);
                    if ($perms->getPermissions($perm, $user)) {
                        // The Horde_Perms::ALL is used if this is a matrix perm,
                        // otherwise it's ignored in the method and the entry is
                        // totally removed.
                        $perm->removeUserPermission($user, Horde_Perms::ALL, true);
                    }
                } catch (Horde_Perms_Exception $e) {
                    Horde::log($e, 'NOTICE');
                    $error = true;
                }
            }

            // Remove any activesync device pairings.
            if ($GLOBALS['conf']['activesync']['enabled']) {
                try {
                    $GLOBALS['injector']->getInstance('Horde_ActiveSyncState')
                        ->removeState(['user' => $user]);
                } catch (Horde_ActiveSync_Exception $e) {
                    Horde::log($e, 'NOTICE');
                    $error = true;
                }
            }

            if ($error) {
                throw new Horde_Exception(sprintf(_("There was an error removing global data for %s. Details have been logged."), $user));
            }
        }

        /**
         */
        public function backup(array $users = [])
        {
            $getUser = function ($user) {
                $backup = new Backup\User($user);
                $this->_backupPrefs($backup, 'horde');
                return $backup;
            };

            return new Backup\Users(new ArrayIterator($users), $getUser);
        }

        /**
         */
        public function restore(Backup\Collection $data)
        {
            if ($data->getType() == 'preferences') {
                return $this->_restorePrefs($data, 'horde');
            }
            return 0;
        }

        protected function _addActiveSyncPerms(&$permissions)
        {
            $prefix = 'activesync:provisioning:';

            $permissions['activesync'] = [
                'title' => _("ActiveSync"),
                'type' => 'boolean',
            ];

            $permissions['activesync:max_devices'] = [
                'title' => _("Maximum number of devices"),
                'type' => 'int',
            ];

            $permissions['activesync:no_gal'] = [
                'title' => _("Prevent access to the Global Address Book"),
                'type' => 'boolean',
            ];

            $permissions['activesync:provisioning'] = [
                'title' => _("Provisioning"),
                'type' => 'enum',
                'params' => [[
                    false => '',
                    'true' => _("Enable"),
                    'allow' => _("Allow non-provisionable devices"),
                    'false' => _("Disable (not recommended)"),
                ]],
            ];

            $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_PIN] = [
                'title' => _("Require PIN"),
                'type' => 'boolean',
            ];

            $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_MINLENGTH] = [
                'title' => _("Minimum PIN length"),
                'type' => 'int',
            ];

            $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_COMPLEXITY] = [
                'title' => _("Password Complexity"),
                'type' => 'enum',
                'params' => [[
                    '' => '',
                    '0' => _("Allow only numeric"),
                    '1' => _("Allow alphanumeric"),
                    '2' => _("Allow any")],
                ],
            ];

            $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_AEFVALUE] = [
                'title' => _("Seconds of inactivity before device should lock"),
                'type' => 'int',
            ];

            $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_MAXFAILEDATTEMPTS] = [
                'title' => _("Failed unlock attempts before device is wiped"),
                'type' => 'int',
            ];

            $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_CODEFREQ] = [
                'title' => _("Codeword frequency"),
                'type' => 'int',
            ];

            // EAS 12.0 and above.
            if ($GLOBALS['conf']['activesync']['version'] >= Horde_ActiveSync::VERSION_TWELVE) {
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ATC] = [
                    'title' => _("Attachment Download"),
                    'type' => 'boolean',
                ];

                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_MAXATCSIZE] = [
                    'title' => _("Maximum attachment size in bytes"),
                    'type' => 'int',
                ];

                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ENCRYPTION] = [
                    'title' => _("SD card encryption"),
                    'type' => 'boolean',
                ];
            }

            if ($GLOBALS['conf']['activesync']['version'] > Horde_ActiveSync::VERSION_TWELVE) {
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ALLOW_SDCARD] = [
                    'title' => _("SD card"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ALLOW_CAMERA] = [
                    'title' => _("Camera"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ALLOW_SMS] = [
                    'title' => _("SMS Text messages"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ALLOW_WIFI] = [
                    'title' => _("Wifi"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ALLOW_BLUETOOTH] = [
                    'title' => _("Bluetooth"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ALLOW_POPIMAP] = [
                    'title' => _("POP/IMAP Email accounts"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ALLOW_BROWSER] = [
                    'title' => _("Web browser"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_REQUIRE_SMIME_ENCRYPTED] = [
                    'title' => _("Require S/MIME Encryption"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_REQUIRE_SMIME_SIGNED] = [
                    'title' => _("Require S/MIME Signature"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_DEVICE_ENCRYPTION] = [
                    'title' => _("Device encryption"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ALLOW_HTML] = [
                    'title' => _("HTML Email"),
                    'type' => 'boolean',
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_MAX_EMAIL_AGE] = [
                    'title' => _("Maximum Email age"),
                    'type' => 'enum',
                    'params' => [[
                        '' => '',
                        '0' => _("Sync all"),
                        '1' => _("1 Day"),
                        '2' => _("3 Days"),
                        '3' => _("1 Week"),
                        '4' => _("2 Weeks"),
                        '5' => _("1 Month")],
                    ],
                ];
                $permissions[$prefix . Horde_ActiveSync_Policies::POLICY_ROAMING_NOPUSH] = [
                    'title' => _("No push while roaming"),
                    'type' => 'boolean',
                ];
            }
        }

    }
}
