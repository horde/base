<?php

declare(strict_types=1);

namespace Horde\Horde\Traits;

use Psr\Http\Message\ResponseInterface;
use Horde\Http\Response;
use Horde\Http\StreamFactory;

/**
 * HTML Response Building Trait
 *
 * Provides helper methods for building HTML responses with proper escaping
 * and headers. Centralizes HTML response creation logic.
 *
 * Code outside horde/base should use the equivalent trait
 * {@see \Horde\Core\Controller\Traits\HtmlResponseTrait} from horde/core.
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
trait HtmlResponseTrait
{
    /**
     * Escape HTML entities for safe output
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    protected function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Create HTML response
     *
     * @param string $html HTML content
     * @param int $status HTTP status code
     * @return ResponseInterface
     */
    protected function htmlResponse(
        string $html,
        int $status = 200
    ): ResponseInterface {
        $streamFactory = new StreamFactory();
        $response = new Response();
        $stream = $streamFactory->createStream($html);

        return $response
            ->withBody($stream)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    }
}
