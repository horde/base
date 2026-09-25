<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Service\Exception\OAuthTokenRefreshException;
use Horde\Core\Service\ServiceNotAuthorizedException;
use Horde\Core\Service\ServiceAuthorization;
use Horde\Core\Service\ServicePurpose;
use Horde\Core\Service\TokenGrant;
use Horde\OAuth\Client\ScopeSet;

/** Concrete ServiceAuthorization implementation. */
class HordeServiceAuthorization implements ServiceAuthorization
{
    public function __construct(
        private readonly string $userId,
        private readonly string $providerId,
        private readonly ServicePurpose $purpose,
        private readonly TokenGrant $grant,
        private readonly ScopeSet $requiredScopes,
    ) {}

    public function userId(): string
    {
        return $this->userId;
    }

    public function providerId(): string
    {
        return $this->providerId;
    }

    public function purpose(): ServicePurpose
    {
        return $this->purpose;
    }

    public function grant(): TokenGrant
    {
        return $this->grant;
    }

    public function isSatisfied(): bool
    {
        return $this->grant->covers($this->requiredScopes);
    }

    public function getAccessToken(): string
    {
        if (!$this->isSatisfied()) {
            throw new ServiceNotAuthorizedException(
                $this->userId,
                $this->providerId,
                $this->purpose,
                $this->requiredScopes
            );
        }

        try {
            return $this->grant->getAccessToken();
        } catch (OAuthTokenRefreshException $e) {
            throw $e;
        }
    }

    public function requiredScopes(): ScopeSet
    {
        return $this->requiredScopes;
    }
}
