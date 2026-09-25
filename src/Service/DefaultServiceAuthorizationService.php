<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\ServiceAuthorization;
use Horde\Core\Service\ServiceAuthorizationRepository;
use Horde\Core\Service\ServiceAuthorizationService;
use Horde\Core\Service\ServiceNotAuthorizedException;
use Horde\Core\Service\ServicePurpose;
use Horde\Core\Service\TokenGrantRepository;
use Horde\Core\Service\GrantStrategy;
use Horde\Core\Service\UnsupportedPurposeException;
use Horde\Core\Service\Exception\OAuthProviderConfigNotFoundException;
use Horde\Core\Uri\RouteUrlWriter;
use Horde\OAuth\Client\OAuth2Client;
use Horde\OAuth\Client\OAuthFlowData;
use Horde\OAuth\Client\OAuthFlowStore;
use Horde\OAuth\Client\PkceGenerator;
use Horde\OAuth\Client\ProviderConfig;
use Horde\OAuth\Client\ScopeSet;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;

/** Default implementation of ServiceAuthorizationService. */
class DefaultServiceAuthorizationService implements ServiceAuthorizationService
{
    public function __construct(
        private readonly ServiceAuthorizationRepository $authRepo,
        private readonly TokenGrantRepository $grantRepo,
        private readonly OAuthProviderConfigRepository $providerConfigRepo,
        private readonly OAuthFlowStore $flowStore,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly RouteUrlWriter $urlWriter,
    ) {}

    public function get(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
    ): ServiceAuthorization {
        $auth = $this->authRepo->find($userId, $providerId, $purpose);

        if ($auth === null) {
            throw new ServiceNotAuthorizedException(
                $userId,
                $providerId,
                $purpose,
                new ScopeSet()
            );
        }

        $requiredScopes = $this->getRequiredScopes($providerId, $purpose);

        if (!$auth->grant()->covers($requiredScopes)) {
            $missingScopes = $this->subtractScopes($requiredScopes, $auth->grant()->grantedScopes());
            throw new ServiceNotAuthorizedException(
                $userId,
                $providerId,
                $purpose,
                $missingScopes
            );
        }

        return $auth;
    }

    public function initiate(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
        string $returnUrl,
        ?string $requestingApp = null,
    ): ?UriInterface {
        $requiredScopes = $this->getRequiredScopes($providerId, $purpose);

        $scopesToRequest = $requiredScopes;

        // Additive strategy: try to reuse existing shared grant
        if ($purpose->grantStrategy() === GrantStrategy::Additive) {
            $existingGrant = $this->grantRepo->findShared($userId, $providerId);

            if ($existingGrant !== null && $existingGrant->covers($requiredScopes)) {
                // Grant already covers required scopes, create auth directly
                $auth = new ConcreteServiceAuthorization(
                    userId: $userId,
                    providerId: $providerId,
                    purpose: $purpose,
                    grant: $existingGrant,
                    requiredScopes: $requiredScopes,
                );
                $this->authRepo->save($auth);
                return null;
            }

            if ($existingGrant !== null) {
                // Need to extend existing grant with additional scopes
                $scopesToRequest = new ScopeSet(...array_unique([
                    ...$existingGrant->grantedScopes()->toArray(),
                    ...$requiredScopes->toArray()
                ]));
            }
        }

        // Generate PKCE parameters
        $verifier = PkceGenerator::generateVerifier();
        $challenge = PkceGenerator::computeChallenge($verifier);
        $state = bin2hex(random_bytes(32));

        // Encode purpose + strategy into flowType
        $flowType = $purpose->identifier() . ':' . $purpose->grantStrategy()->value;

        $this->flowStore->save($state, new OAuthFlowData(
            state: $state,
            providerId: $providerId,
            pkceVerifier: $verifier,
            flowType: $flowType,
            createdAt: time(),
            redirectUrl: $returnUrl,
            requestingApp: $requestingApp,
        ));

        // Build authorization URL
        $providerConfig = $this->providerConfigRepo->get($providerId);
        $redirectUri = $this->urlWriter->absoluteUrlFor('SettingsOAuthCallback');
        $client = $this->buildOAuth2Client($providerConfig, $redirectUri);

        return $client->getAuthorizationUrl(
            scopes: $scopesToRequest->toArray(),
            state: $state,
            codeChallenge: $challenge,
            codeChallengeMethod: 'S256',
        );
    }

    /** Internal helper - userId should be obtained from authenticated session by controller. */
    public function handleCallback(string $code, OAuthFlowData $flowData): ServiceAuthorization
    {
        // Interface requires this signature but doesn't provide userId
        // Controller should call handleCallbackWithUser() instead
        throw new \RuntimeException(
            'handleCallback() cannot determine userId from flow data. ' .
            'Controller must use internal handleCallbackWithUser() method.'
        );
    }

    /** Internal method called by controller with userId from authenticated session. */
    public function handleCallbackWithUser(string $userId, string $code, OAuthFlowData $flowData): ServiceAuthorization
    {
        // Parse purpose from flowType
        $parts = explode(':', $flowData->flowType, 2);
        if (count($parts) !== 2) {
            throw new \RuntimeException('Invalid flowType format: ' . $flowData->flowType);
        }
        $purpose = ServicePurpose::of($parts[0], GrantStrategy::from($parts[1]));

        $providerConfig = $this->providerConfigRepo->get($flowData->providerId);
        $redirectUri = $this->urlWriter->absoluteUrlFor('SettingsOAuthCallback');
        $client = $this->buildOAuth2Client($providerConfig, $redirectUri);

        // Exchange authorization code for tokens
        $tokenSet = $client->exchangeCode($code, $flowData->pkceVerifier);

        $grantedScopes = ScopeSet::fromSpaceSeparated($tokenSet->scope ?? '');

        if ($purpose->grantStrategy() === GrantStrategy::Additive) {
            $existingGrant = $this->grantRepo->findShared($userId, $flowData->providerId);

            if ($existingGrant !== null) {
                // Update existing shared grant
                if ($existingGrant instanceof MutableTokenGrant) {
                    $existingGrant->updateTokenSet($tokenSet);
                }
                $this->grantRepo->update($existingGrant);
                $grant = $existingGrant;
            } else {
                // Create new shared grant
                $grant = new MutableTokenGrant(
                    grantId: bin2hex(random_bytes(16)),
                    userId: $userId,
                    providerId: $flowData->providerId,
                    tokenSet: $tokenSet,
                    grantedScopes: $grantedScopes,
                    isShared: true,
                    repository: $this->grantRepo,
                );
                $this->grantRepo->save($grant);
            }
        } else {
            // Isolated: create new grant
            $grant = new MutableTokenGrant(
                grantId: bin2hex(random_bytes(16)),
                userId: $userId,
                providerId: $flowData->providerId,
                tokenSet: $tokenSet,
                grantedScopes: $grantedScopes,
                isShared: false,
                repository: $this->grantRepo,
            );
            $this->grantRepo->save($grant);
        }

        // Create ServiceAuthorization
        $requiredScopes = $this->getRequiredScopes($flowData->providerId, $purpose);
        $auth = new ConcreteServiceAuthorization(
            userId: $userId,
            providerId: $flowData->providerId,
            purpose: $purpose,
            grant: $grant,
            requiredScopes: $requiredScopes,
        );
        $this->authRepo->save($auth);

        return $auth;
    }

    public function revoke(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
    ): void {
        $auth = $this->authRepo->find($userId, $providerId, $purpose);
        if ($auth === null) {
            return; // Idempotent
        }

        $this->authRepo->delete($auth);
        // Grant is intentionally left intact
    }

    public function revokeAll(
        string $userId,
        string $providerId,
        bool $revokeAtProvider = false,
    ): void {
        $grants = $this->grantRepo->findAll($userId, $providerId);

        if ($revokeAtProvider && count($grants) > 0) {
            $providerConfig = $this->providerConfigRepo->get($providerId);
            $redirectUri = $this->urlWriter->absoluteUrlFor('SettingsOAuthCallback');
            $client = $this->buildOAuth2Client($providerConfig, $redirectUri);

            foreach ($grants as $grant) {
                $refreshToken = $grant->tokenSet()->refreshToken;
                if ($refreshToken !== null) {
                    try {
                        $client->revokeToken($refreshToken, 'refresh_token');
                    } catch (\Throwable $e) {
                        // Continue revoking other tokens
                        error_log("Failed to revoke token at provider: {$e->getMessage()}");
                    }
                }
            }
        }

        $this->authRepo->deleteAll($userId, $providerId);

        foreach ($grants as $grant) {
            $this->grantRepo->delete($grant);
        }
    }

    private function getRequiredScopes(string $providerId, ServicePurpose $purpose): ScopeSet
    {
        try {
            $providerConfig = $this->providerConfigRepo->get($providerId);
        } catch (OAuthProviderConfigNotFoundException $e) {
            throw new UnsupportedPurposeException($providerId, $purpose);
        }

        // Check for purpose in the purposes map
        if (isset($providerConfig['purposes'][$purpose->identifier()])) {
            return ScopeSet::fromSpaceSeparated($providerConfig['purposes'][$purpose->identifier()]);
        }

        // For 'login' purpose, fall back to default_scopes
        if ($purpose->identifier() === 'login' && !empty($providerConfig['default_scopes'])) {
            return ScopeSet::fromSpaceSeparated($providerConfig['default_scopes']);
        }

        throw new UnsupportedPurposeException($providerId, $purpose);
    }

    private function subtractScopes(ScopeSet $required, ScopeSet $granted): ScopeSet
    {
        $missing = array_diff($required->toArray(), $granted->toArray());
        return new ScopeSet(...$missing);
    }

    private function buildOAuth2Client(array $providerConfig, string $redirectUri = ''): OAuth2Client
    {
        $config = ProviderConfig::fromArray($providerConfig);

        return new OAuth2Client(
            provider: $config,
            clientId: $providerConfig['client_id'] ?? '',
            clientSecret: $providerConfig['client_secret'] ?? null,
            redirectUri: $redirectUri,
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
        );
    }
}
