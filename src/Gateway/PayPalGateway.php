<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Gateway;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use Yiisoft\Payments\Model\PaymentIntent;

/**
 * PayPalGateway is a PSR-18/PSR-17 powered adapter for PayPal Orders v2.
 * It implements create, approval URL retrieval, confirm (payment source), capture,
 * refund (capture), and retrieve operations, aligning with the schema.
 */
final class PayPalGateway extends AbstractGateway
{
    /** @var string PayPal REST API client ID. */
    private string $clientId;

    /** @var string PayPal REST API client secret. */
    private string $clientSecret;

    /** @var bool Use sandbox or live API base URL. */
    private bool $sandbox;

    /** @var ClientInterface PSR-18 HTTP client. */
    private ClientInterface $http;

    /** @var RequestFactoryInterface PSR-17 request factory. */
    private RequestFactoryInterface $req;

    /** @var StreamFactoryInterface PSR-17 stream factory. */
    private StreamFactoryInterface $stream;

    /** @var string|null OAuth access token. */
    private ?string $accessToken = null;

    /** @var int Token expiration epoch (renew slightly early). */
    private int $tokenExp = 0;

    /**
     * @param string                  $clientId       PayPal client ID.
     * @param string                  $clientSecret   PayPal client secret.
     * @param bool                    $sandbox        Whether to use sandbox endpoints.
     * @param ClientInterface         $httpClient     PSR-18 HTTP client.
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory.
     * @param StreamFactoryInterface  $streamFactory  PSR-17 stream factory.
     */
    public function __construct(
        string $clientId,
        string $clientSecret,
        bool $sandbox,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->sandbox = $sandbox;
        $this->http = $httpClient;
        $this->req = $requestFactory;
        $this->stream = $streamFactory;
    }

    /**
     * Get the API base URL for the current environment.
     *
     * @return string
     */
    private function base(): string
    {
        return $this->sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    /**
     * Acquire or reuse an OAuth2 token.
     *
     * @return void
     */
    private function auth(): void
    {
        if ($this->accessToken && $this->tokenExp > time()) {
            return;
        }

        $req = $this->req->createRequest('POST', $this->base() . '/v1/oauth2/token')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Accept-Language', 'en_US')
            ->withHeader('Authorization', 'Basic ' . base64_encode("{$this->clientId}:{$this->clientSecret}"))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->stream->createStream('grant_type=client_credentials'));

        $res = $this->http->sendRequest($req);
        $data = json_decode((string)$res->getBody(), true);

        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('Failed to obtain PayPal access token');
        }

        $this->accessToken = $data['access_token'];
        $ttl = (int)($data['expires_in'] ?? 3600);
        $this->tokenExp = time() + $ttl - 60;
    }

    /**
     * Send a JSON request with bearer token and decode response JSON.
     *
     * @param string                 $method HTTP method.
     * @param string                 $path   Relative API path.
     * @param array<string,mixed>|null $body Optional JSON body.
     * @return array<string,mixed> Decoded JSON response.
     */
    private function send(string $method, string $path, ?array $body = null): array
    {
        $this->auth();

        $req = $this->req->createRequest($method, $this->base() . $path)
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->withHeader('Content-Type', 'application/json');

        if ($body !== null) {
            $req = $req->withBody($this->stream->createStream(json_encode($body, JSON_UNESCAPED_SLASHES)));
        }

        $res = $this->http->sendRequest($req);
        $code = $res->getStatusCode();
        $raw = (string)$res->getBody();

        if ($code >= 400) {
            throw new \RuntimeException("PayPal API error: HTTP {$code} {$raw}");
        }

        $json = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON from PayPal API');
        }

        return $json;
    }

    /**
     * @inheritDoc
     */
    public function createPaymentIntent(PaymentIntent $intent): PaymentIntent
    {
        $resp = $this->send('POST', '/v2/checkout/orders', $intent->toArray());
        $intent->orderId    = $resp['id']          ?? null;
        $intent->status     = $resp['status']      ?? null;
        $intent->createTime = $resp['create_time'] ?? null;
        $intent->updateTime = $resp['update_time'] ?? null;
        return $intent;
    }

    /**
     * @inheritDoc
     */
    public function getApprovalUrl(string $orderId): ?string
    {
        $resp = $this->send('GET', "/v2/checkout/orders/{$orderId}");
        foreach ($resp['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                return $link['href'] ?? null;
            }
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function confirmPaymentIntent(string $orderId, array $paymentSource): PaymentIntent
    {
        $resp = $this->send('POST', "/v2/checkout/orders/{$orderId}/confirm", [
            'payment_source' => $paymentSource,
        ]);

        $out = new PaymentIntent();
        $out->orderId    = $resp['id']          ?? $orderId;
        $out->status     = $resp['status']      ?? null;
        $out->createTime = $resp['create_time'] ?? null;
        $out->updateTime = $resp['update_time'] ?? null;
        return $out;
    }

    /**
     * @inheritDoc
     */
    public function capturePaymentIntent(string $orderId): PaymentIntent
    {
        $resp = $this->send('POST', "/v2/checkout/orders/{$orderId}/capture", null);
        $out = new PaymentIntent();
        $out->orderId    = $orderId;
        $out->status     = $resp['status']      ?? null;
        $out->createTime = $resp['create_time'] ?? null;
        $out->updateTime = $resp['update_time'] ?? null;

        // Extract capture IDs from the first purchase unit (adjust if multiple) [web:125]
        $captures = $resp['purchase_units'][0]['payments']['captures'] ?? [];
        $out->captureIds = [];
        foreach ($captures as $cap) {
            if (!empty($cap['id'])) {
                $out->captureIds[] = (string)$cap['id'];
            }
        }

        return $out;
    }

    /**
     * @inheritDoc
     */
    public function createRefund(string $captureId, ?float $amount = null, ?string $currency = null): bool
    {
        $body = null;
        if ($amount !== null) {
            $body = [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => $currency ?: 'USD',
                ],
            ];
        }

        $resp = $this->send('POST', "/v2/payments/captures/{$captureId}/refund", $body);
        return isset($resp['id']);
    }

    /**
     * @inheritDoc
     */
    public function retrievePaymentIntent(string $orderId): PaymentIntent
    {
        $resp = $this->send('GET', "/v2/checkout/orders/{$orderId}");
        $out = new PaymentIntent();
        $out->orderId    = $resp['id']          ?? $orderId;
        $out->status     = $resp['status']      ?? null;
        $out->createTime = $resp['create_time'] ?? null;
        $out->updateTime = $resp['update_time'] ?? null;
        $out->intent     = $resp['intent']      ?? $out->intent;
        return $out;
    }
}
