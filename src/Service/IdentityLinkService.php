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
use Horde\Identity\Exception\IdentityNotFoundException;
use Horde\Identity\Identity;
use Horde\Identity\IdentityRepository;
use Horde\Identity\IdentityRole;
use Horde\Identity\IdentityStatus;

class IdentityLinkService
{
    public function __construct(
        private readonly AuthLinkRepository $linkRepo,
        private readonly IdentityRepository $identityRepo,
    ) {}

    public function resolveByCredentials(string $provider, string $externalId): ?Identity
    {
        $link = $this->linkRepo->resolve($provider, $externalId);
        if ($link === null) {
            return null;
        }

        try {
            return $this->identityRepo->get($link->identityId);
        } catch (IdentityNotFoundException) {
            return null;
        }
    }

    public function resolveByUsername(string $username): ?Identity
    {
        return $this->resolveByCredentials(AuthLink::PROVIDER_LOCAL, $username);
    }

    public function linkToIdentity(
        string $identityId,
        string $provider,
        string $externalId,
        ?string $email = null,
        ?string $displayName = null,
        ?array $metadata = null,
    ): AuthLink {
        if (!$this->identityRepo->exists($identityId)) {
            throw new IdentityNotFoundException(
                sprintf('Identity "%s" not found', $identityId)
            );
        }

        $link = new AuthLink(
            linkId: 0,
            identityId: $identityId,
            provider: $provider,
            externalId: $externalId,
            externalEmail: $email,
            externalDisplayName: $displayName,
            linkedAt: new DateTimeImmutable(),
            lastUsedAt: null,
            metadata: $metadata,
        );

        return $this->linkRepo->save($link);
    }

    public function coupleLocalUser(string $identityId, string $username): AuthLink
    {
        return $this->linkToIdentity($identityId, AuthLink::PROVIDER_LOCAL, $username);
    }

    public function unlinkFromIdentity(string $provider, string $externalId): void
    {
        $this->linkRepo->deleteByProviderAndExternalId($provider, $externalId);
    }

    /** @return list<AuthLink> */
    public function getLinksForIdentity(string $identityId): array
    {
        return $this->linkRepo->findByIdentity($identityId);
    }

    public function supersede(string $oldIdentityId, string $newIdentityId): void
    {
        $old = $this->identityRepo->get($oldIdentityId);
        $links = $this->linkRepo->findByIdentity($oldIdentityId);

        $displayName = $old->displayName;
        $primaryEmail = $old->primaryEmail;
        $providerNames = [];

        foreach ($links as $link) {
            if ($link->provider !== AuthLink::PROVIDER_LOCAL) {
                $providerNames[] = $link->provider;
            }
            if ($displayName === null && $link->externalDisplayName !== null) {
                $displayName = $link->externalDisplayName;
            }
            if ($primaryEmail === null && $link->externalEmail !== null) {
                $primaryEmail = $link->externalEmail;
            }
        }

        if ($displayName !== null && !empty($providerNames)) {
            $displayName .= ' (' . implode(', ', $providerNames) . ')';
        } elseif ($displayName === null && !empty($providerNames)) {
            $displayName = implode(', ', $providerNames);
        }

        $this->identityRepo->save(new Identity(
            id: $old->id,
            role: $old->role,
            status: $old->status,
            displayName: $displayName,
            primaryEmail: $primaryEmail,
            emails: $old->emails,
            supersededBy: $newIdentityId,
            createdAt: $old->createdAt,
            updatedAt: new DateTimeImmutable(),
        ));
    }

    public function touchLastUsed(string $provider, string $externalId): void
    {
        $link = $this->linkRepo->resolve($provider, $externalId);
        if ($link !== null) {
            $this->linkRepo->updateLastUsed($link->linkId, new DateTimeImmutable());
        }
    }

    /** @return list<Identity> */
    public function findSupersededBy(string $identityId): array
    {
        return $this->identityRepo->findSupersededBy($identityId);
    }

    public function resolveOrCreate(
        string $provider,
        string $externalId,
        ?string $email = null,
        ?string $displayName = null,
        IdentityRole $role = IdentityRole::Collaborator,
    ): Identity {
        $existing = $this->resolveByCredentials($provider, $externalId);
        if ($existing !== null) {
            return $existing;
        }

        $now = new DateTimeImmutable();
        $identity = new Identity(
            id: bin2hex(random_bytes(16)),
            role: $role,
            status: IdentityStatus::Active,
            displayName: $displayName,
            primaryEmail: $email,
            emails: $email !== null ? [$email] : [],
            supersededBy: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->identityRepo->save($identity);

        $this->linkRepo->save(new AuthLink(
            linkId: 0,
            identityId: $identity->id,
            provider: $provider,
            externalId: $externalId,
            externalEmail: $email,
            externalDisplayName: $displayName,
            linkedAt: $now,
            lastUsedAt: null,
            metadata: null,
        ));

        return $identity;
    }
}
