<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A tiny PSR-18 HTTP client for unit tests.
 *
 * Features:
 * - Response queue (to simulate multiple sequential HTTP calls, e.g. OAuth token + API call)
 * - JSON and raw-body responses
 * - Captures the last request for assertions
 */
final class TestHttpClient implements ClientInterface
{
    /** @var array<int,array{status:int,headers:array<string,array<int,string>>,body:string}> */
    private array $queue = [];

    /** @var array{method:string,uri:string,headers:array<string,array<int,string>>,body:string}|array{} */
    public array $lastRequest = [];

    public function __construct(private Psr17Factory $factory)
    {
    }

    /**
     * Backward-compatible helper: queues a single JSON response with 200 status.
     *
     * @param array<string,mixed>|null $response
     */
    public function setNextResponse(?array $response): void
    {
        $this->queueJsonResponse($response ?? ['error' => 'No response configured'], 200);
    }

    /**
     * @param array<string,mixed>|null $response
     * @param array<string,array<int,string>> $headers
     */
    public function queueJsonResponse(?array $response, int $statusCode = 200, array $headers = []): void
    {
        $body = json_encode($response ?? [], JSON_THROW_ON_ERROR);

        $this->queueRawResponse(
            $body,
            $statusCode,
            $headers + ['Content-Type' => ['application/json']]
        );
    }

    /**
     * @param array<string,array<int,string>> $headers
     */
    public function queueRawResponse(string $body, int $statusCode = 200, array $headers = []): void
    {
        $this->queue[] = [
            'status' => $statusCode,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $request->getHeaders(),
            'body' => (string) $request->getBody(),
        ];

        $spec = array_shift($this->queue);
        if ($spec === null) {
            $spec = [
                'status' => 200,
                'headers' => ['Content-Type' => ['application/json']],
                'body' => json_encode(['error' => 'No response configured'], JSON_THROW_ON_ERROR),
            ];
        }

        $stream = $this->factory->createStream($spec['body']);
        $response = $this->factory->createResponse($spec['status'])->withBody($stream);

        foreach ($spec['headers'] as $name => $values) {
            foreach ($values as $value) {
                $response = $response->withAddedHeader($name, $value);
            }
        }

        return $response;
    }
}
