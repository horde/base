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

/**
 * Immutable value object representing the outcome of an authentication attempt.
 */
final class LoginResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $userId = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $passwordChangeRequired = false,
        public readonly bool $hasUpdateCapability = false,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $accessToken = null,
        public readonly ?string $refreshToken = null,
        public readonly ?int $expiresAt = null,
    ) {}
}
