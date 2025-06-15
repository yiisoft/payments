<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Gateways;

use Yiisoft\Payments\Exceptions\InvalidRequestException;
use Yiisoft\Payments\Exceptions\PaymentException;
use Yiisoft\Payments\PaymentGatewayInterface;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractGateway implements PaymentGatewayInterface
{
    protected const API_VERSION = '1.0.0';

    public function __construct(
        protected ClientInterface $httpClient,
        protected RequestFactoryInterface $requestFactory,
        protected StreamFactoryInterface $streamFactory,
        protected ?LoggerInterface $logger = null
    ) {
    }

    abstract protected function getBaseUri(): string;

    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->$level($message, $context);
        }
    }

    /**
     * @return \Psr\Http\Message\RequestInterface
     */
    protected function createRequest(string $method, string $endpoint, array $data = [])
    {
        $uri = rtrim($this->getBaseUri(), '/') . '/' . ltrim($endpoint, '/');
        $request = $this->requestFactory->createRequest($method, $uri);

        if (!empty($data)) {
            $stream = $this->streamFactory->createStream(json_encode($data, JSON_THROW_ON_ERROR));
            $request = $request
                ->withBody($stream)
                ->withHeader('Content-Type', 'application/json');
        }

        return $request
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'PaymentGateway/' . self::API_VERSION);
    }

    protected function sendRequest($request): array
    {
        try {
            $response = $this->httpClient->sendRequest($request);
            $responseBody = (string) $response->getBody();
            $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

            if ($response->getStatusCode() >= 400) {
                $this->handleErrorResponse($data, $response->getStatusCode());
            }

            return $data;
        } catch (\JsonException $e) {
            $this->log('error', 'Failed to decode JSON response', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to decode payment gateway response', 0, $e);
        } catch (\Throwable $e) {
            $this->log('error', 'Payment gateway request failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function handleErrorResponse(array $response, int $statusCode): void
    {
        $error = $response['error'] ?? [];
        $message = $error['message'] ?? 'An error occurred with the payment gateway';
        $code = $error['code'] ?? null;
        $type = $error['type'] ?? null;
        $declineCode = $error['decline_code'] ?? null;
        $param = $error['param'] ?? null;

        switch ($statusCode) {
            case 400:
                throw new \Yiisoft\Payments\Exceptions\InvalidRequestException(
                    $message,
                    $code,
                    $type,
                    $declineCode,
                    $param,
                    $statusCode
                );
            // Add more specific exceptions as needed
            default:
                throw new \Yiisoft\Payments\Exceptions\PaymentException(
                    $message,
                    $code,
                    $type,
                    $declineCode,
                    $param,
                    $statusCode
                );
        }
    }
}
