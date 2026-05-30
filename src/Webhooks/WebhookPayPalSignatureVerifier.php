<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;
use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;
use Yiisoft\Payments\Endpoints\PayPalEndpoints;

/**
 * Default PayPal webhook signature verifier based on PayPal's verify-webhook-signature API.
 */
final class WebhookPayPalSignatureVerifier implements WebhookPayPalSignatureVerifierInterface
{
    private const API_VERSION = '1.0.0';

    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private bool $sandbox,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private PayPalEndpoints $endpoints = new PayPalEndpoints(),
    ) {
        if (trim($clientId) === '') {
            throw new InvalidArgumentException('PayPal client ID must be a non-empty string.');
        }

        if (trim($clientSecret) === '') {
            throw new InvalidArgumentException('PayPal client secret must be a non-empty string.');
        }
    }

    public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
    {
        if (trim($webhookId) === '') {
            throw new InvalidArgumentException('PayPal webhook ID must be a non-empty string.');
        }

        try {
            $webhookEvent = json_decode($input->rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return self::failure('paypal_webhook_event_json_invalid', 'PayPal webhook event body must be valid JSON.');
        }

        if (!is_array($webhookEvent)) {
            return self::failure('paypal_webhook_event_json_invalid', 'PayPal webhook event body must decode to a JSON object.');
        }

        try {
            $verificationResponse = $this->sendJsonRequest(
                method: 'POST',
                endpoint: '/v1/notifications/verify-webhook-signature',
                payload: [
                    'auth_algo' => $this->getFirstHeaderValue($input, 'PayPal-Auth-Algo'),
                    'cert_url' => $this->getFirstHeaderValue($input, 'PayPal-Cert-Url'),
                    'transmission_id' => $this->getFirstHeaderValue($input, 'PayPal-Transmission-Id'),
                    'transmission_sig' => $this->getFirstHeaderValue($input, 'PayPal-Transmission-Sig'),
                    'transmission_time' => $this->getFirstHeaderValue($input, 'PayPal-Transmission-Time'),
                    'webhook_id' => $webhookId,
                    'webhook_event' => $webhookEvent,
                ],
                withAuthorization: true,
            );
        } catch (Throwable $e) {
            return self::failure(
                'paypal_signature_verification_failed',
                'PayPal webhook signature verification request failed: ' . $e->getMessage(),
            );
        }

        $verificationStatus = $verificationResponse['verification_status'] ?? null;
        if ($verificationStatus === 'SUCCESS') {
            return WebhookValidationResult::success();
        }

        if ($verificationStatus === 'FAILURE') {
            return self::failure('paypal_signature_verification_failed', 'PayPal webhook signature verification failed.');
        }

        return self::failure(
            'paypal_signature_verification_response_invalid',
            'PayPal webhook signature verification response does not contain a supported verification status.',
        );
    }

    private function getFirstHeaderValue(WebhookInput $input, string $headerName): string
    {
        foreach ($input->getHeader($headerName) as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sendJsonRequest(string $method, string $endpoint, array $payload, bool $withAuthorization): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $request = $this->requestFactory
            ->createRequest($method, $this->getBaseUri() . $endpoint)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('User-Agent', 'PaymentGateway/' . self::API_VERSION)
            ->withBody($this->streamFactory->createStream($body));

        if ($withAuthorization) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->getAccessToken());
        }

        $response = $this->httpClient->sendRequest($request);
        $responseBody = (string) $response->getBody();

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf(
                'PayPal API returned HTTP %d during webhook signature verification.',
                $response->getStatusCode(),
            ));
        }

        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('PayPal API returned a non-object JSON response.');
        }

        return $data;
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->accessTokenExpiresAt) {
            return $this->accessToken;
        }

        $request = $this->requestFactory
            ->createRequest('POST', $this->getBaseUri() . '/v1/oauth2/token')
            ->withHeader('Authorization', 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret))
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('User-Agent', 'PaymentGateway/' . self::API_VERSION)
            ->withBody($this->streamFactory->createStream('grant_type=client_credentials'));

        $response = $this->httpClient->sendRequest($request);
        $responseBody = (string) $response->getBody();

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf(
                'PayPal API returned HTTP %d while requesting an access token.',
                $response->getStatusCode(),
            ));
        }

        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['access_token']) || !is_string($data['access_token']) || $data['access_token'] === '') {
            throw new \RuntimeException('PayPal access token response does not contain a non-empty access token.');
        }

        $expiresIn = isset($data['expires_in']) && is_int($data['expires_in']) ? $data['expires_in'] : 3600;
        $this->accessToken = $data['access_token'];
        $this->accessTokenExpiresAt = time() + max(60, $expiresIn - 60);

        return $this->accessToken;
    }

    private function getBaseUri(): string
    {
        return rtrim($this->endpoints->getBaseUri($this->sandbox), '/');
    }

    private static function failure(string $code, string $message): WebhookValidationResult
    {
        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode($code),
            message: $message,
        ));
    }
}
