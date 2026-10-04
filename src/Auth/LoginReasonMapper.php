<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */

namespace Horde\Horde\Auth;

use Horde_Auth;
use Horde_Core_Auth_Application;

/**
 * Static maps between Horde_Auth reason constants, URL-safe
 * error-code strings, user-facing messages and CSS alert classes.
 *
 * Factored out from login.php and ResponsiveLoginController.
 *
 */
class LoginReasonMapper
{
    /**
     * Map a URL-safe logout/error reason string to a Horde_Auth constant.
     *
     * Handles both the "logout_reason" vocabulary (logout, session, …)
     * and the PRG "error" vocabulary (badlogin, required, secondfactor, …).
     *
     * @return int|null  The Horde_Auth::REASON_* constant or null when
     *                   the string is not recognised.
     */
    public static function stringToConstant(string $reason): ?int
    {
        if (is_numeric($reason)) {
            return (int) $reason;
        }

        return match ($reason) {
            'logout'       => Horde_Auth::REASON_LOGOUT,
            'badlogin'     => Horde_Auth::REASON_BADLOGIN,
            'expired'      => Horde_Auth::REASON_EXPIRED,
            'locked'       => Horde_Auth::REASON_LOCKED,
            'failed'       => Horde_Auth::REASON_FAILED,
            'message'      => Horde_Auth::REASON_MESSAGE,
            'session'      => Horde_Auth::REASON_SESSION,
            // PRG error codes that don't map 1:1 to a dedicated constant:
            'required'     => Horde_Auth::REASON_BADLOGIN,
            'secondfactor' => Horde_Auth::REASON_BADLOGIN,
            default        => null,
        };
    }

    /**
     * Map a Horde_Auth constant to a URL-safe error-code string
     * suitable for PRG redirects.
     */
    public static function constantToErrorCode(int $constant): string
    {
        return match ($constant) {
            Horde_Auth::REASON_BADLOGIN => 'badlogin',
            Horde_Auth::REASON_EXPIRED  => 'expired',
            Horde_Auth::REASON_LOCKED   => 'locked',
            // This might not be entirely accurate
            Horde_Auth::REASON_MESSAGE  => 'secondfactor',
            default                     => 'failed',
        };
    }

    /**
     * Resolve a Horde_Auth/Horde_Core_Auth_Application reason constant
     * to a translated user-facing message and a CSS alert class.
     *
     * @param int         $reason     Horde_Auth::REASON_* constant.
     * @param string|null $fallback   Optional backend message (e.g. from
     *                                $auth->getError(true) or a logout_msg
     *                                query parameter). Used for LOCKED /
     *                                MESSAGE when no better text exists.
     *
     * @return array{string, string}  [message, alertCssClass]
     */
    public static function constantToUserMessage(int $reason, ?string $fallback = null): array
    {
        $message = match ($reason) {
            Horde_Auth::REASON_SESSION
                => _("Your session has expired. Please login again."),
            Horde_Core_Auth_Application::REASON_SESSIONIP
                => _("Your Internet Address has changed since the beginning of your session. To protect your security, you must login again."),
            Horde_Core_Auth_Application::REASON_BROWSER
                => _("Your browser appears to have changed since the beginning of your session. To protect your security, you must login again."),
            Horde_Core_Auth_Application::REASON_SESSIONMAXTIME
                => _("Your session length has exceeded the maximum amount of time allowed. Please login again."),
            Horde_Auth::REASON_LOGOUT
                => _("You have been logged out."),
            Horde_Auth::REASON_FAILED
                => _("Login failed."),
            Horde_Auth::REASON_BADLOGIN
                => _("Login failed because your username or password was entered incorrectly."),
            Horde_Auth::REASON_EXPIRED
                => _("Your login has expired."),
            Horde_Auth::REASON_LOCKED,
            Horde_Auth::REASON_MESSAGE
                => $fallback ?? _("Login failed."),
            default => null,
        };

        if ($message === null) {
            $message = _("Login failed.");
        }

        $alertClass = match ($reason) {
            Horde_Auth::REASON_LOGOUT,
            Horde_Auth::REASON_SESSION,
            Horde_Core_Auth_Application::REASON_SESSIONIP,
            Horde_Core_Auth_Application::REASON_BROWSER,
            Horde_Core_Auth_Application::REASON_SESSIONMAXTIME
                => 'alert-info',
            Horde_Auth::REASON_MESSAGE
                => 'alert-success',
            default => 'alert-error',
        };

        return [$message, $alertClass];
    }

    /**
     * Resolve a Post-Redirect-Get (PRG) error-code string (from a ?error= query parameter)
     * to a translated user-facing message and CSS alert class.
     *
     * @param string      $errorCode     URL-safe error code.
     * @param string|null $errorMessage  Optional backend-supplied message
     *                                   (e.g. from a ?msg= query parameter).
     *
     * @return array{string, string}  [message, alertCssClass]
     */
    public static function errorCodeToUserMessage(string $errorCode, ?string $errorMessage = null): array
    {
        $message = match ($errorCode) {
            'badlogin'     => _("Login failed because your username or password was entered incorrectly."),
            'expired'      => _("Your login has expired."),
            'locked'       => _("Your account has been locked."),
            'required'     => _("Please enter a username and password."),
            'secondfactor' => $errorMessage ?? _("Second factor authentication failed."),
            'failed'       => _("Login failed."),
            'logout'       => _("You have been logged out."),
            default        => _("An error occurred. Please try again."),
        };

        $alertClass = ($errorCode === 'logout') ? 'alert-info' : 'alert-error';

        return [$message, $alertClass];
    }
}
