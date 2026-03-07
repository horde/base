<?php

declare(strict_types=1);

namespace Horde\Horde\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde\Http\Response;

class DebugController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        $data = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $request->getHeaders(),
            'header_line_content_type' => $request->getHeaderLine('Content-Type'),
            'has_header_content_type' => $request->hasHeader('Content-Type'),
            'server_params' => $request->getServerParams(),
            'body_size' => $request->getBody()->getSize(),
            'body_content' => (string) $request->getBody(),
        ];

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
