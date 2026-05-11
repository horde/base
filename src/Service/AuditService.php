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

namespace Horde\Horde\Service;

use Psr\Log\LoggerInterface;

/**
 * Standardized audit logging for authentication events.
 *
 * Provides consistent log format matching the established login.php patterns.
 */
class AuditService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Log a successful authentication.
     */
    public function logLoginSuccess(
        string $user,
        ?string $app,
        string $ip,
        ?string $forwardedFor,
    ): void {
        $this->logger->notice(sprintf(
            'Login success for %s to %s (%s)%s',
            $user,
            $app ?? 'horde',
            $ip,
            $this->formatForwardedFor($forwardedFor),
        ));
    }

    /**
     * Log a failed authentication attempt.
     */
    public function logLoginFailure(
        string $user,
        ?string $app,
        string $ip,
        ?string $forwardedFor,
    ): void {
        $this->logger->error(sprintf(
            'FAILED LOGIN for %s to %s (%s)%s',
            $user,
            $app ?? 'horde',
            $ip,
            $this->formatForwardedFor($forwardedFor),
        ));
    }

    /**
     * Log a user logout.
     */
    public function logLogout(
        string $user,
        string $ip,
        ?string $forwardedFor,
    ): void {
        $this->logger->notice(sprintf(
            'User %s logged out of Horde (%s)%s',
            $user,
            $ip,
            $this->formatForwardedFor($forwardedFor),
        ));
    }

    private function formatForwardedFor(?string $forwardedFor): string
    {
        if (empty($forwardedFor)) {
            return '';
        }
        return ' (forwarded for [' . $forwardedFor . '])';
    }
}
