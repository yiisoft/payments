<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Gateway;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Yiisoft\Payments\Model\PaymentIntent;
use Yiisoft\Payments\Model\PaymentMethod;
use Yiisoft\Payments\Model\PaymentMethodType;

/**
 * PayPal Orders v2 / Payments v2 gateway implementation.
 */
final class PayPalGateway extends AbstractGateway
{
    private ?string $accessToken = null;
    private ?int $accessTokenExpiresAt = null;

    public function __construct(
        PaymentMethod $paymentMethod,
        array $config,
        \Psr\Http\Client\ClientInterface $http,
        \Psr\Http\Message\RequestFactoryInterface $requestFactory,
        \Psr\Http\Message\StreamFactoryInterface $streamFactory,
        \Psr\Log\LoggerInterface $logger,
    ) {
        if ($paymentMethod->providerType !== PaymentMethodType::PAYPAL) {
            throw new \InvalidArgumentException('PaymentMethod must use PAYPAL provider type.');
        }

        parent::__construct($paymentMethod, $config, $http, $requestFactory, $streamFactory, $logger);
    }

    private function baseUrl(): string
    {
        $sandbox = (bool)$this->getConfig('sandbox', true);
        if ($sandbox) {
            return (string)$this->getConfig('sandbox_url');
        }

        return (string)$this->getConfig('live_url');
    }

    /**
     * Ensures there is a valid OAuth2 access token for PayPal. [web:65][web:82]
     */
    private function ensureAccessToken(): void
    {
        $now = time();
        if ($this->accessToken !== null && $this->accessTokenExpiresAt !== null && $now < $this->accessTokenExpiresAt) {
            return;
        }

        $url = $this->baseUrl() . '/v1/oauth2/token';
        $clientId = (string)$this->getConfig('client_id');
        $clientSecret = (string)$this->getConfig('client_secret');
        $body = http_build_query(['grant_type' => 'client_credentials']);

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Accept-Language', 'en_US')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $request = $request->withBody($this->streamFactory->createStream($body));

        $basicAuth = base64_encode($clientId . ':' . $clientSecret);
        $request = $request->withHeader('Authorization', 'Basic ' . $basicAuth);

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('PayPal auth HTTP error', ['exception' => $e]);
            throw $e;
        }

        $status = $response->getStatusCode();
        $rawBody = (string)$response->getBody();

        if ($status < 200 || $status >= 300) {
            $this->logger->error('PayPal auth failed', ['status' => $status, 'body' => $rawBody]);
            throw new \RuntimeException('PayPal auth failed: HTTP ' . $status);
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            throw new \RuntimeException('PayPal auth response is not JSON.');
        }

        $this->accessToken = (string)($data['access_token'] ?? '');
        $expiresIn = (int)($data['expires_in'] ?? 300);
        $this->accessTokenExpiresAt = $now + $expiresIn - 30;

        $this->logger->info('PayPal access token obtained', [
            'expires_in' => $expiresIn,
        ]);
    }

    /**
     * Creates an authorized JSON request using the stored access token.
     *
     * @param string $method HTTP method.
     * @param string $path Relative API path (starting with /).
     * @param array<string,mixed>|null $payload Optional JSON payload.
     */
    private function authorizedRequest(string $method, string $path, ?array $payload = null): RequestInterface
    {
        $this->ensureAccessToken();

        $url = $this->baseUrl() . $path;

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken);

        if ($payload !== null) {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
            $request = $request->withBody($this->streamFactory->createStream($json));
        }

        return $request;
    }

    /**
     * Sends an HTTP request and decodes JSON response.
     *
     * @return array<string,mixed>
     */
    private function sendJson(RequestInterface $request): array
    {
        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('PayPal HTTP error', ['exception' => $e]);
            throw $e;
        }

        $status = $response->getStatusCode();
        $body = (string)$response->getBody();

        $this->logger->info('PayPal API response', [
            'status' => $status,
            'body' => $body,
        ]);

        $data = $body !== '' ? json_decode($body, true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('PayPal API error: HTTP ' . $status);
        }

        return $data;
    }

    /**
     * Creates a PayPal order with intent CAPTURE and returns approval URL. [web:69][web:81]
     */
    public function createPayment(PaymentIntent $intent): array
    {
        $requestBody = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $intent->id,
                    'amount' => [
                        'currency_code' => $intent->currency,
                        'value' => number_format($intent->amount, 2, '.', ''),
                    ],
                    'description' => $intent->description ?? '',
                ],
            ],
            'application_context' => [
                'return_url' => (string)$this->getConfig('return_url'),
                'cancel_url' => (string)$this->getConfig('cancel_url'),
                'user_action' => 'PAY_NOW',
            ],
        ];

        $request = $this->authorizedRequest('POST', '/v2/checkout/orders', $requestBody);
        $data = $this->sendJson($request);

        $approveUrl = null;
        foreach ($data['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approveUrl = $link['href'] ?? null;
                break;
            }
        }

        $status = strtolower((string)($data['status'] ?? 'created'));

        $this->logger->info('PayPal order created', [
            'status' => $status,
            'id' => $data['id'] ?? null,
        ]);

        return [
            'success' => true,
            'status' => $status,
            'redirect_url' => $approveUrl,
            'raw' => $data,
        ];
    }

    /**
     * Captures a previously created order after user approval. [web:69][web:68][web:88]
     *
     * $providerData must include 'order_id', which is the PayPal order ID.
     */
    public function capture(PaymentIntent $intent, array $providerData = []): array
    {
        $orderId = (string)($providerData['order_id'] ?? '');
        if ($orderId === '') {
            throw new \InvalidArgumentException('PayPal capture requires order_id in providerData.');
        }

        $request = $this->authorizedRequest('POST', '/v2/checkout/orders/' . $orderId . '/capture', []);
        $data = $this->sendJson($request);

        $status = strtolower((string)($data['status'] ?? 'unknown'));

        // Retrieve capture ID from response: purchase_units[0].payments.captures[0].id [web:69][web:67]
        $captureId = null;
        $unit0 = $data['purchase_units'][0] ?? null;
        if (is_array($unit0)) {
            $capture0 = $unit0['payments']['captures'][0] ?? null;
            if (is_array($capture0)) {
                $captureId = $capture0['id'] ?? null;
            }
        }

        if ($captureId !== null) {
            $intent->metadata['paypal_capture_id'] = $captureId;
        }

        $this->logger->info('PayPal order captured', [
            'order_id' => $orderId,
            'status' => $status,
            'capture_id' => $captureId,
        ]);

        $successful = in_array($status, ['completed', 'approved', 'captured'], true);

        return [
            'success' => $successful,
            'status' => $status,
            'raw' => $data,
        ];
    }

    /**
     * Executes a refund for a given capture. [web:67]
     *
     * The capture ID must be stored in $intent->metadata['paypal_capture_id'].
     */
    public function refund(PaymentIntent $intent, float $amount, ?string $currency = null): array
    {
        $captureId = (string)($intent->metadata['paypal_capture_id'] ?? '');
        if ($captureId === '') {
            throw new \InvalidArgumentException('PayPal refund requires paypal_capture_id in intent metadata.');
        }

        $refundCurrency = $currency ?? $intent->currency;
        $body = [
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency_code' => $refundCurrency,
            ],
        ];

        $request = $this->authorizedRequest('POST', '/v2/payments/captures/' . $captureId . '/refund', $body);
        $data = $this->sendJson($request);

        $status = strtolower((string)($data['status'] ?? 'unknown'));

        $this->logger->info('PayPal refund executed', [
            'capture_id' => $captureId,
            'amount' => $amount,
            'currency' => $refundCurrency,
            'status' => $status,
        ]);

        $successful = in_array($status, ['completed', 'pending'], true);

        return [
            'success' => $successful,
            'status' => $status,
            'raw' => $data,
        ];
    }
}
