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

interface AuthLinkRepository
{
    public function resolve(string $provider, string $externalId): ?AuthLink;

    /** @return list<AuthLink> */
    public function findByIdentity(string $identityId): array;

    public function save(AuthLink $link): AuthLink;

    public function delete(int $linkId): void;

    public function deleteByProviderAndExternalId(string $provider, string $externalId): void;

    public function updateLastUsed(int $linkId, DateTimeImmutable $at): void;
}
