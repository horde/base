<?php

declare(strict_types=1);

namespace Horde\Horde\Traits;

use Psr\Http\Message\ResponseInterface;
use Horde\Http\Response;

/**
 * Redirect Response Building Trait
 *
 * Provides helper methods for building HTTP redirect responses.
 * Centralizes redirect logic for consistency.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
trait RedirectResponseTrait
{
    /**
     * Create redirect response
     *
     * @param string $url Target URL
     * @param int $status HTTP status code (302 = temporary, 301 = permanent)
     * @return ResponseInterface
     */
    protected function redirect(
        string $url,
        int $status = 302
    ): ResponseInterface {
        $response = new Response();
        return $response
            ->withStatus($status)
            ->withHeader('Location', $url);
    }

    /**
     * Redirect to login with optional return URL
     *
     * @param string $loginUrl Login page URL
     * @param string|null $returnUrl Optional URL to return to after login
     * @return ResponseInterface
     */
    protected function redirectToLogin(
        string $loginUrl,
        ?string $returnUrl = null
    ): ResponseInterface {
        if ($returnUrl) {
            $loginUrl .= '?url=' . urlencode($returnUrl);
        }
        return $this->redirect($loginUrl);
    }
}
