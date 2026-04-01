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
        $error = isset($response['error']) && is_array($response['error'])
            ? $response['error']
            : $response;

        $message = $this->extractErrorMessage($error)
            ?? $this->extractErrorMessage($response)
            ?? 'An error occurred with the payment gateway';

        $code = $this->extractErrorField($error, ['code', 'Code', 'error_code', 'ErrorCode', 'ResultCode']);
        $type = $this->extractErrorField($error, ['type', 'Type', 'error_type', 'ErrorType']);
        $declineCode = $this->extractErrorField($error, ['decline_code', 'DeclineCode']);
        $param = $this->extractErrorField($error, ['param', 'Param', 'parameter', 'Parameter']);
        $details = [
            'response' => $response,
        ];

        switch ($statusCode) {
            case 400:
                throw new InvalidRequestException(
                    $message,
                    $code,
                    $type,
                    $declineCode,
                    $param,
                    $details,
                    $statusCode
                );
            default:
                throw new PaymentException(
                    $message,
                    $code,
                    $type,
                    $declineCode,
                    $param,
                    $details,
                    $statusCode
                );
        }
    }

    private function extractErrorMessage(array $payload): ?string
    {
        $message = $this->extractErrorField(
            $payload,
            ['message', 'Message', 'description', 'Description', 'error_description', 'error_message', 'Error']
        );

        if ($message !== null && $message !== '') {
            return $message;
        }

        $errors = $payload['errors'] ?? $payload['Errors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            $first = reset($errors);
            if (is_array($first)) {
                return $this->extractErrorMessage($first);
            }

            if (is_scalar($first)) {
                return (string) $first;
            }
        }

        $error = $payload['error'] ?? null;
        if (is_scalar($error) && $error !== '') {
            return (string) $error;
        }

        return null;
    }

    /**
     * @param list<string> $keys
     */
    private function extractErrorField(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_scalar($value) && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
