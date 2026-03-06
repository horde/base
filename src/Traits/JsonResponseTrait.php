<?php

declare(strict_types=1);

namespace Horde\Horde\Traits;

use Psr\Http\Message\ResponseInterface;
use Horde\Http\Response;

/**
 * JSON Response Building Trait
 *
 * Provides helper methods for building JSON responses with proper headers
 * and status codes. Centralizes JSON response creation logic.
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
trait JsonResponseTrait
{
    /**
     * Create JSON response
     *
     * @param array $data Response data
     * @param int $status HTTP status code
     * @param int $jsonFlags JSON encoding flags
     * @return ResponseInterface
     */
    protected function jsonResponse(
        array $data,
        int $status = 200,
        int $jsonFlags = JSON_THROW_ON_ERROR
    ): ResponseInterface {
        $response = new Response();
        $response = $response->withStatus($status);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($data, $jsonFlags));

        return $response;
    }

    /**
     * Create error JSON response
     *
     * @param string $message Error message
     * @param int $status HTTP status code (default 400)
     * @param string $errorCode Error code identifier
     * @return ResponseInterface
     */
    protected function jsonError(
        string $message,
        int $status = 400,
        string $errorCode = 'error'
    ): ResponseInterface {
        return $this->jsonResponse([
            'error' => $errorCode,
            'message' => $message,
        ], $status);
    }

    /**
     * Create unauthorized JSON response (401)
     *
     * @param string $message Error message
     * @return ResponseInterface
     */
    protected function jsonUnauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return $this->jsonError($message, 401, 'unauthorized');
    }
}
