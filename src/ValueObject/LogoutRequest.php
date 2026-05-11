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

namespace Horde\Horde\ValueObject;

use Horde_Auth;

/**
 * Immutable value object representing a logout request.
 */
final class LogoutRequest
{
    public function __construct(
        public readonly ?string $csrfToken,
        public readonly int $reason = Horde_Auth::REASON_LOGOUT,
        public readonly ?string $message = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $anchorString = null,
        public readonly string $remoteAddr = '',
        public readonly ?string $forwardedFor = null,
    ) {}
}
