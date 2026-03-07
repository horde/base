<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Observability;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Horde\Observability\Readiness;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Unit Test: Readiness Check
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(Readiness::class)]
class ReadinessTest extends TestCase
{
    private StreamFactoryInterface $streamFactory;
    private ResponseFactoryInterface $responseFactory;
    private ServerRequestInterface $request;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
    }

    public function testProcessReturns200(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with('1')
            ->willReturn($stream);

        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(200)
            ->willReturn($response);

        $response->expects($this->once())
            ->method('withBody')
            ->with($stream)
            ->willReturn($response);

        $readiness = new Readiness($this->streamFactory, $this->responseFactory);
        $result = $readiness->process($this->request, $this->handler);

        $this->assertSame($response, $result);
    }

    public function testProcessCreatesStreamWithOne(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withBody')->willReturn($response);

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with($this->identicalTo('1'))
            ->willReturn($stream);

        $this->responseFactory->method('createResponse')
            ->willReturn($response);

        $readiness = new Readiness($this->streamFactory, $this->responseFactory);
        $readiness->process($this->request, $this->handler);
    }

    public function testProcessDoesNotCallHandler(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withBody')->willReturn($response);

        $this->streamFactory->method('createStream')->willReturn($stream);
        $this->responseFactory->method('createResponse')->willReturn($response);

        // Handler should never be called - this is a terminal middleware
        $this->handler->expects($this->never())
            ->method('handle');

        $readiness = new Readiness($this->streamFactory, $this->responseFactory);
        $readiness->process($this->request, $this->handler);
    }

    public function testProcessSetsBodyCorrectly(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $this->streamFactory->method('createStream')->willReturn($stream);
        $this->responseFactory->method('createResponse')->willReturn($response);

        $response->expects($this->once())
            ->method('withBody')
            ->with($this->identicalTo($stream))
            ->willReturn($response);

        $readiness = new Readiness($this->streamFactory, $this->responseFactory);
        $readiness->process($this->request, $this->handler);
    }
}
