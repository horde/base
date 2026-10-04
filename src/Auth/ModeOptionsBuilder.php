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

/**
 * Build the view-mode options shown in the login form's "Mode" dropdown.
 *
 * Extracted from the duplicated option-building logic in login.php and
 * LoginService::renderModeSelector() (former duplication 6 in
 * LOGIN_UNIFICATION.md).
 *
 * Returns a flat value => translated-label map.  Consumers are free to
 * wrap it into whichever format their rendering path expects (e.g.
 * LoginFormFieldRenderer's nested `['name' => ...]` structure, or direct
 * `<option>` rendering in LoginService).
 */
class ModeOptionsBuilder
{
    /**
     * Build the available view-mode options.
     *
     * The canonical option list always includes `mobile_nojs` so that
     * the client-side JavaScript can detect and remove it on page load.
     * This fixes the former divergence where login.php included it but
     * the /auth/login route did not.
     *
     * @param bool $includeBasic    Include "Basic" mode
     *                              (conf `user.select_basic_view`).
     * @param bool $includeMinimal  Include "Mobile (Minimal)" mode
     *                              (conf `user.select_minimal_view`).
     *
     * @return array<string, string>  value => translated label
     */
    public static function build(
        bool $includeBasic = false,
        bool $includeMinimal = false,
    ): array {
        $options = ['auto' => _("Automatic")];

        if ($includeBasic) {
            $options['basic'] = _("Basic");
        }

        $options['dynamic'] = _("Dynamic");

        if ($includeMinimal) {
            $options['mobile'] = _("Mobile (Minimal)");
        }

        // Always include mobile_nojs so JavaScript can remove it on load.
        $options['mobile_nojs'] = _("Mobile (No JavaScript)");

        $options['smartmobile'] = _("Mobile (Smartphone/Tablet)");

        return $options;
    }
}
