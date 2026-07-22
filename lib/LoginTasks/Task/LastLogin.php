<?php

use NetDNS2\Exception as NetDNS2Exception;

/**
 * Login task to output last login information.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */
class Horde_LoginTasks_Task_LastLogin extends Horde_LoginTasks_Task
{
    /**
     * The interval at which to run the task.
     *
     * @var integer
     */
    public $interval = Horde_LoginTasks::EVERY;

    /**
     * Display type.
     *
     * @var integer
     */
    public $display = Horde_LoginTasks::DISPLAY_NONE;

    /**
     * Perform all functions for this task.
     */
    public function execute()
    {
        global $injector, $notification, $prefs, $registry;

        /* Fetch the user's last login time. */
        $old_login = @unserialize($prefs->getValue('last_login'), ['allowed_classes' => false]);

        // Normalize host field: handle both string and Net_DNS2\Data\Domain object.
        // Historical data may contain Net_DNS2\Data\Domain objects from PTR queries.
        // Ensure string format for consistent behavior across Horde versions.
        if (is_array($old_login) && isset($old_login['host']) && is_object($old_login['host'])) {
            $old_login['host'] = (string) $old_login['host'];
        }

        /* Set the timezone to the current default. */
        $registry->setTimeZone();

        /* Display it, if we have a notification object and the
         * show_last_login preference is active. */
        if (isset($notification) && $prefs->getValue('show_last_login')) {
            if (empty($old_login['time'])) {
                $notification->push(_("Last login: Never"), 'horde.message');
            } else {
                /* Format date and time separately.
                 *
                 * The date_format and time_format prefs each hold a
                 * value that Horde\Date\Format::formatDate() understands
                 * on its own — either a NAMED_STYLES key (e.g. 'short',
                 * 'medium-time') or a full ICU pattern string
                 * (e.g. 'yyyy-MM-dd', 'h:mm:ss a'). Concatenating the
                 * two into '<date_format> (<time_format>)' produces a
                 * string that is neither a named style nor a valid ICU
                 * pattern; IntlDateFormatter then interprets it
                 * character-by-character as ICU field chars and
                 * silently returns garbage rather than raising an
                 * error. Reported in base#137: default 'short' +
                 * 'medium-time' rendered as just '01'.
                 *
                 * Fix: call formatDate() twice and compose the visible
                 * string ourselves. */
                $locale = $GLOBALS['language'] ?? 'en_US';
                $datePart = Horde\Date\Format::formatDate(
                    $old_login['time'],
                    $prefs->getValue('date_format'),
                    $locale,
                );
                $timePart = Horde\Date\Format::formatDate(
                    $old_login['time'],
                    $prefs->getValue('time_format'),
                    $locale,
                );
                $when = $datePart . ' (' . $timePart . ')';

                if (empty($old_login['host'])) {
                    $notification->push(
                        sprintf(_("Last login: %s"), $when),
                        'horde.message',
                    );
                } else {
                    $notification->push(
                        sprintf(_("Last login: %s from %s"), $when, $old_login['host']),
                        'horde.message',
                    );
                }
            }
        }

        /* Set the user's last_login information. */
        $host = empty($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? $_SERVER['REMOTE_ADDR']
            : $_SERVER['HTTP_X_FORWARDED_FOR'];

        if ($dns = $injector->getInstance('Net_DNS2_Resolver')) {
            $ptrdname = $host;
            try {
                if ($response = $dns->query($host, 'PTR')) {
                    foreach ($response->answer as $val) {
                        if (isset($val->ptrdname)) {
                            $ptrdname = $val->ptrdname;
                            break;
                        }
                    }
                }
            } catch (NetDNS2Exception $e) {
            }
        } else {
            $ptrdname = @gethostbyaddr($host);
        }

        // Explicitly cast to string to prevent Net_DNS2\Data\Domain objects
        // from being serialized. This ensures cross-version compatibility.
        $prefs->setValue('last_login', serialize([
            'host' => (string) $ptrdname,
            'time' => time(),
        ]));
    }

}
