<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TestHttpClient implements ClientInterface
{
    private ?array $nextResponse = null;
    public array $lastRequest = [];

    public function __construct(private Psr17Factory $factory)
    {
    }

    public function setNextResponse(?array $response): void
    {
        $this->nextResponse = $response;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $request->getHeaders(),
            'body' => (string) $request->getBody(),
        ];

        $response = $this->nextResponse ?? ['error' => 'No response configured'];
        
        $stream = $this->factory->createStream(json_encode($response, JSON_THROW_ON_ERROR));
        return $this->factory->createResponse(200)->withBody($stream);
    }
}
