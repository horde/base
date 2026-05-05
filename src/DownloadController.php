<?php

declare(strict_types=1);

namespace Horde\Horde;

use Horde\Util\Variables;
use Horde_Registry;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * PSR-15 controller for Horde download service requests.
 *
 * Mirrors services/download/index.php behavior for routed traffic.
 */
class DownloadController implements RequestHandlerInterface
{
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $body = $request->getParsedBody();
        $params = is_array($body) ? array_merge($query, $body) : $query;
        $vars = new Variables($params);

        if (!isset($vars->app)) {
            return $this->responseFactory->createResponse(400, 'Missing app parameter');
        }

        if (isset($vars->fn)) {
            $vars->filename = ltrim((string) $vars->fn, '/');
            unset($vars->fn);
        }

        try {
            $res = $this->registry->callAppMethod($vars->app, 'download', [
                'args' => [$vars],
            ]);
        } catch (Throwable) {
            return $this->responseFactory->createResponse(404, 'Download failed');
        }

        if (!is_array($res) || !array_key_exists('data', $res)) {
            return $this->responseFactory->createResponse(404, 'Download not found');
        }

        $filename = $res['name'] ?? ($vars->filename ?? 'download');
        $type = $res['type'] ?? 'application/octet-stream';

        if (is_resource($res['data'])) {
            $tmp = fopen('php://temp', 'w+b');
            if ($tmp === false) {
                return $this->responseFactory->createResponse(500, 'Cannot stream download');
            }
            rewind($res['data']);
            stream_copy_to_stream($res['data'], $tmp);
            fclose($res['data']);
            rewind($tmp);
            $data = stream_get_contents($tmp);
            fclose($tmp);
        } else {
            $data = (string) $res['data'];
        }

        $size = array_key_exists('size', $res) ? (int) $res['size'] : strlen($data);
        $dispositionName = str_replace(['"', "\r", "\n"], ['_', '', ''], (string) $filename);

        $response = $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', $type)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $dispositionName . '"')
            ->withHeader('Content-Length', (string) $size)
            ->withHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->withBody($this->streamFactory->createStream($data));

        return $response;
    }
}

