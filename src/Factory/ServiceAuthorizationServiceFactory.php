<?php

declare(strict_types=1);

namespace Horde\Horde\Factory;

use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\ServiceAuthorizationRepository;
use Horde\Core\Service\ServiceAuthorizationService;
use Horde\Core\Service\TokenGrantRepository;
use Horde\Core\Uri\RouteUrlWriter;
use Horde\Horde\Service\DefaultServiceAuthorizationService;
use Horde\Injector\Injector;
use Horde\OAuth\Client\OAuthFlowStore;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class ServiceAuthorizationServiceFactory
{
    public function create(Injector $injector): ServiceAuthorizationService
    {
        return new DefaultServiceAuthorizationService(
            authRepo: $injector->getInstance(ServiceAuthorizationRepository::class),
            grantRepo: $injector->getInstance(TokenGrantRepository::class),
            providerConfigRepo: $injector->getInstance(OAuthProviderConfigRepository::class),
            flowStore: $injector->getInstance(OAuthFlowStore::class),
            httpClient: $injector->getInstance(ClientInterface::class),
            requestFactory: $injector->getInstance(RequestFactoryInterface::class),
            streamFactory: $injector->getInstance(StreamFactoryInterface::class),
            urlWriter: $injector->getInstance(RouteUrlWriter::class),
        );
    }
}
