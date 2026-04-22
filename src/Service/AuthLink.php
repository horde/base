<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Horde\Service;

use DateTimeImmutable;

final class AuthLink
{
    public const PROVIDER_LOCAL = 'local:user';

    public function __construct(
        public readonly int $linkId,
        public readonly string $identityId,
        public readonly string $provider,
        public readonly string $externalId,
        public readonly ?string $externalEmail,
        public readonly ?string $externalDisplayName,
        public readonly DateTimeImmutable $linkedAt,
        public readonly ?DateTimeImmutable $lastUsedAt,
        public readonly ?array $metadata,
    ) {}
}
