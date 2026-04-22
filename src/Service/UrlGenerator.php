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
use Horde\Routes\Mapper;
use Horde\Routes\Utils;

class UrlGenerator
{
    private Utils $utils;

    public function __construct(
        private readonly Mapper $mapper,
        private readonly string $webroot,
        string $serverName = '',
        string $serverPort = '',
        int $useSsl = 0,
    ) {
        $this->mapper->environ['SCRIPT_NAME'] = rtrim($webroot, '/');

        $https = '';
        if ($useSsl === Horde::SSL_ALWAYS) {
            $https = 'on';
        } elseif ($useSsl === Horde::SSL_AUTO && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $https = 'on';
        }

        $host = $serverName;
        if ($serverPort !== ''
            && !(($https === 'on' && (int) $serverPort === 443)
              || ($https === '' && (int) $serverPort === 80))) {
            $host .= ':' . $serverPort;
        }

        $this->mapper->environ['HTTP_HOST'] = $host;
        $this->mapper->environ['SERVER_NAME'] = $serverName;
        $this->mapper->environ['HTTPS'] = $https;

        $this->utils = new Utils($this->mapper);
    }

    public function urlFor(string $routeName, array $params = []): string
    {
        return $this->utils->urlFor($routeName, $params);
    }

    public function absoluteUrlFor(string $routeName, array $params = []): string
    {
        $params['qualified'] = true;

        return $this->utils->urlFor($routeName, $params);
    }

    public function getWebroot(): string
    {
        return $this->webroot;
    }
}
