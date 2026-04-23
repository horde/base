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

namespace Horde\Horde\Factory;

use Horde\Core\Service\OAuthHttpClientService;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\OAuthTokenService;
use Horde\Horde\Service\DefaultOAuthHttpClientService;
use Horde_Injector;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class OAuthHttpClientServiceFactory
{
    public function create(Horde_Injector $injector): OAuthHttpClientService
    {
        return new DefaultOAuthHttpClientService(
            tokenService: $injector->getInstance(OAuthTokenService::class),
            providerConfig: $injector->getInstance(OAuthProviderConfigRepository::class),
            httpClient: $injector->getInstance(ClientInterface::class),
            requestFactory: $injector->getInstance(RequestFactoryInterface::class),
            streamFactory: $injector->getInstance(StreamFactoryInterface::class),
        );
    }
}
