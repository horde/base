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

use Horde\Core\Horde;
use Horde\Core\Uri\RoutesProvider;

class UrlGenerator
{
    public function __construct(
        private readonly RoutesProvider $provider,
        private readonly string $webroot,
        private readonly array $environ = [],
    ) {}

    public function urlFor(string $routeName, array $params = []): string
    {
        return $this->provider->generateNamedPath($routeName, $params) ?? '';
    }

    public function absoluteUrlFor(string $routeName, array $params = []): string
    {
        $path = $this->provider->generateNamedPath($routeName, $params);
        if ($path === null) {
            return '';
        }

        $host = $this->environ['HTTP_HOST']
            ?? $this->environ['SERVER_NAME']
            ?? 'localhost';

        $scheme = (!empty($this->environ['HTTPS']) && $this->environ['HTTPS'] !== 'off')
            ? 'https'
            : 'http';

        return $scheme . '://' . $host . $path;
    }

    public function getWebroot(): string
    {
        return $this->webroot;
    }
}
