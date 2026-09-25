<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Service\Exception\OAuthTokenRefreshException;
use Horde\Core\Service\TokenGrant;
use Horde\OAuth\Client\ScopeSet;
use Horde\OAuth\Client\TokenSet;

/** Mutable TokenGrant implementation for internal use in repositories and services. */
class MutableTokenGrant implements TokenGrant
{
    public function __construct(
        private readonly string $grantId,
        private readonly string $userId,
        private readonly string $providerId,
        private TokenSet $tokenSet,
        private ScopeSet $grantedScopes,
        private readonly bool $isShared = false,
        private readonly TokenGrantRepository|null $repository = null,
    ) {}

    public function grantId(): string
    {
        return $this->grantId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function providerId(): string
    {
        return $this->providerId;
    }

    public function grantedScopes(): ScopeSet
    {
        return $this->grantedScopes;
    }

    public function covers(ScopeSet $required): bool
    {
        return $this->grantedScopes->contains($required);
    }

    public function getAccessToken(): string
    {
        if (!$this->isExpired()) {
            return $this->tokenSet->accessToken;
        }

        if ($this->repository === null) {
            throw new OAuthTokenRefreshException(
                'Token expired and no repository available for refresh',
                $this->userId,
                $this->providerId
            );
        }

        // Refresh will be handled by the service layer when needed
        throw new OAuthTokenRefreshException(
            'Token expired; refresh required',
            $this->userId,
            $this->providerId,
            $this
        );
    }

    public function isExpired(): bool
    {
        if ($this->tokenSet->expiresAt === null) {
            return false;
        }
        return time() >= $this->tokenSet->expiresAt;
    }

    public function tokenSet(): TokenSet
    {
        return $this->tokenSet;
    }

    public function isShared(): bool
    {
        return $this->isShared;
    }

    /** Update token data after refresh or scope extension. */
    public function updateTokenSet(TokenSet $newTokenSet): void
    {
        $this->tokenSet = $newTokenSet;

        // Merge scopes if new set contains additional grants
        if (!empty($newTokenSet->scope)) {
            $newScopes = ScopeSet::fromSpaceSeparated($newTokenSet->scope);
            $this->grantedScopes = new ScopeSet(...array_unique([
                ...$this->grantedScopes->toArray(),
                ...$newScopes->toArray()
            ]));
        }
    }
}
