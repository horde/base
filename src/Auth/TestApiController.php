<?php
declare(strict_types=1);

namespace Horde\Horde\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde\Http\Response;

class TestApiController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        
        $body = (string) $request->getBody();
        $data = [
            'body_content' => $body,
            'body_length' => strlen($body),
            'parsed_body' => $request->getParsedBody(),
            'method' => $request->getMethod(),
        ];
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
