<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Gateways;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Payments\Endpoints\PayPalEndpoints;
use Yiisoft\Payments\Exceptions\PaymentException;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;

/**
 * PayPal gateway implementation based on PayPal REST API:
 * - OAuth2 token:        /v1/oauth2/token
 * - Checkout Orders API: /v2/checkout/orders
 * - Refunds API:         /v2/payments/captures/{capture_id}/refund
 *
 * Notes about API v2:
 * - PayPal does not provide a "Customers API" similar to Stripe. The customer-related methods in this gateway are
 *   implemented as local no-ops to preserve the module's common interface. See method-level docs.
 * - PayPal has 2 main order intents:
 *     - CAPTURE   (immediate capture after payer approval)
 *     - AUTHORIZE (separate authorization step; capture can be performed later)
 *
 * Amount format:
 * - Internally this library uses integer minor units (e.g. cents). PayPal API expects decimal strings (e.g. "10.00").
 * - This gateway converts between these representations assuming 2 decimal places for currencies that use decimals.
 */
final class PayPalGateway extends AbstractGateway
{
    /** Access token cache. */
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    private PayPalEndpoints $endpoints;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private bool $sandbox,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null,
        ?PayPalEndpoints $endpoints = null
    ) {
        parent::__construct($httpClient, $requestFactory, $streamFactory, $logger);
        $this->endpoints = $endpoints ?? new PayPalEndpoints();
    }

    protected function getBaseUri(): string
    {
        return $this->endpoints->getBaseUri($this->sandbox);
    }

    /**
     * PayPal does not expose a generic "Customer" resource compatible with this library's interface.
     *
     * This method returns the given model with an assigned ID if it doesn't have one. No API call is made.
     * The returned ID is stable only within the calling application (store it yourself if needed).
     */
    public function createCustomer(Customer $customer): Customer
    {
        if ($customer->id !== null) {
            return $customer;
        }

        return new Customer(
            id: 'paypal_customer_' . bin2hex(random_bytes(8)),
            email: $customer->email,
            name: $customer->name,
            phone: $customer->phone,
            address: $customer->address,
            metadata: $customer->metadata,
            description: $customer->description,
        );
    }

    /**
     * PayPal does not expose a generic "Customer" resource compatible with this library's interface.
     *
     * This method returns a placeholder customer with the provided ID.
     */
    public function retrieveCustomer(string $customerId): Customer
    {
        return new Customer(id: $customerId);
    }

    /**
     * PayPal does not expose a generic "Customer" resource compatible with this library's interface.
     *
     * This method returns the input customer unchanged.
     */
    public function updateCustomer(Customer $customer): Customer
    {
        return $customer;
    }

    /**
     * PayPal does not expose a generic "Customer" resource compatible with this library's interface.
     *
     * This method is a no-op.
     */
    public function deleteCustomer(string $customerId): void
    {
        // Intentionally no-op.
    }

    /**
     * Creates a PayPal Order (Checkout Orders API v2).
     *
     * The resulting PaymentIntent has:
     * - id: PayPal Order ID
     * - status: PayPal Order status (e.g. CREATED, APPROVED, COMPLETED)
     * - nextAction.redirect_to_url.url: PayPal approval URL (when present)
     *
     * Optional metadata keys used by this gateway:
     * - return_url: URL where PayPal will redirect the payer after approval (web flow)
     * - cancel_url: URL where PayPal will redirect the payer if they cancel (web flow)
     */
    public function createPaymentIntent(PaymentIntent $paymentIntent): PaymentIntent
    {
        $currency = strtoupper($paymentIntent->currency);
        $amount = self::formatAmount($paymentIntent->amount);

        $data = [
            'intent' => $paymentIntent->captureMethod ? 'AUTHORIZE' : 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $amount,
                ],
            ]],
        ];

        // Allow passing PayPal-specific payment_source (e.g. advanced card payments) via metadata.
        if (isset($paymentIntent->metadata['paypal_payment_source']) && is_array($paymentIntent->metadata['paypal_payment_source'])) {
            $data['payment_source'] = $paymentIntent->metadata['paypal_payment_source'];
        }

        if ($paymentIntent->description !== null) {
            $data['purchase_units'][0]['description'] = $paymentIntent->description;
        }

        if (!empty($paymentIntent->metadata)) {
            // A convenient way to pass application-specific identifiers into PayPal.
            if (isset($paymentIntent->metadata['order_id'])) {
                $data['purchase_units'][0]['custom_id'] = (string) $paymentIntent->metadata['order_id'];
            }
        }

        $returnUrl = $paymentIntent->metadata['return_url'] ?? null;
        $cancelUrl = $paymentIntent->metadata['cancel_url'] ?? null;
        if ($returnUrl !== null && $cancelUrl !== null) {
            $data['application_context'] = [
                'return_url' => (string) $returnUrl,
                'cancel_url' => (string) $cancelUrl,
                'user_action' => 'PAY_NOW',
            ];
        }

        $response = $this->sendRequest(
            $this->createRequest('POST', '/v2/checkout/orders', $data)
        );

        return $this->mapOrderToPaymentIntent($response, $paymentIntent);
    }

    /**
     * Retrieves PayPal Order data.
     */
    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        $order = $this->sendRequest(
            $this->createRequest('GET', "/v2/checkout/orders/{$paymentIntentId}")
        );

        return $this->mapOrderToPaymentIntent($order, null);
    }

    /**
     * Confirms payment intent.
     *
     * For PayPal web flows, payer approval happens outside of the API (via approval link).
     * This method simply re-fetches the current order state.
     */
    public function confirmPaymentIntent(string $paymentIntentId, array $params = []): PaymentIntent
    {
        return $this->retrievePaymentIntent($paymentIntentId);
    }

    /**
     * Captures a payment intent.
     *
     * - For CAPTURE orders: performs /v2/checkout/orders/{id}/capture
     * - For AUTHORIZE orders: performs /v2/checkout/orders/{id}/authorize (result contains authorization ID)
     *
     * If you already have an authorization ID and want to capture it explicitly, pass it as:
     *   $this->capturePaymentIntent($orderId, ['authorization_id' => '...'])
     */
        public function capturePaymentIntent(string $paymentIntentId, array $params = []): PaymentIntent
    {
        // Optional: confirm payment source (used for advanced card payments flows).
        //
        // This is intentionally implemented via $params to avoid changing the public interface. To enable it:
        // - create the order as usual (createPaymentIntent)
        // - then call capturePaymentIntent($orderId, [
        //       'confirm_payment_source' => true,
        //       'payment_source' => [...], // e.g. ['card' => [...]]
        //   ])
        if (!empty($params['confirm_payment_source'])) {
            $paymentSource = $params['payment_source'] ?? null;
            if (!is_array($paymentSource)) {
                throw new PaymentException(
                    'PayPal confirm-payment-source requested but params[\'payment_source\'] is missing or invalid.',
                    'paypal_invalid_params',
                    'paypal',
                    null,
                    'payment_source',
                    400
                );
            }

            $this->sendRequest(
                $this->createRequest('POST', "/v2/checkout/orders/{$paymentIntentId}/confirm-payment-source", [
                    'payment_source' => $paymentSource,
                ])
            );
        }

        if (isset($params['authorization_id'])) {
            $authId = (string) $params['authorization_id'];

            $capture = $this->sendRequest(
                $this->createRequest('POST', "/v2/payments/authorizations/{$authId}/capture", $params['capture'] ?? [])
            );

            // In this mode we don't have order context; return a PaymentIntent-like object with useful metadata.
            return PaymentIntent::fromArray([
                'id' => $paymentIntentId,
                'status' => 'CAPTURED',
                'metadata' => [
                    'authorization_id' => $authId,
                    'capture' => $capture,
                    'capture_id' => $this->extractCaptureId($capture),
                ],
            ]);
        }

        $order = $this->sendRequest(
            $this->createRequest('GET', "/v2/checkout/orders/{$paymentIntentId}")
        );

        $intent = $order['intent'] ?? null;

        if ($intent === 'AUTHORIZE') {
            $auth = $this->sendRequest(
                $this->createRequest('POST', "/v2/checkout/orders/{$paymentIntentId}/authorize")
            );

            $authorizationId = $this->extractAuthorizationId($auth);

            return PaymentIntent::fromArray([
                'id' => $paymentIntentId,
                'status' => $auth['status'] ?? 'AUTHORIZED',
                'metadata' => [
                    'authorization' => $auth,
                    'authorization_id' => $authorizationId,
                ],
            ]);
        }

        $capture = $this->sendRequest(
            $this->createRequest('POST', "/v2/checkout/orders/{$paymentIntentId}/capture")
        );

        $captureId = $this->extractCaptureId($capture);

        return PaymentIntent::fromArray([
            'id' => $paymentIntentId,
            'status' => $capture['status'] ?? 'COMPLETED',
            'metadata' => [
                'capture' => $capture,
                'capture_id' => $captureId,
            ],
        ]);
    }

public function cancelPaymentIntent(string $paymentIntentId, array $params = []): PaymentIntent
    {
        try {
            $order = $this->sendRequest(
                $this->createRequest('GET', "/v2/checkout/orders/{$paymentIntentId}")
            );
        } catch (PaymentException) {
            return PaymentIntent::fromArray(['id' => $paymentIntentId, 'status' => 'VOIDED']);
        }

        $order['status'] = 'VOIDED';
        return $this->mapOrderToPaymentIntent($order, null);
    }

    /**
     * PayPal does not expose a generic "PaymentMethod" resource compatible with this library's interface.
     *
     * This method returns the given model with an assigned ID if it doesn't have one. No API call is made.
     */
    public function createPaymentMethod(PaymentMethod $paymentMethod): PaymentMethod
    {
        if ($paymentMethod->id !== null) {
            return $paymentMethod;
        }

        return new PaymentMethod(
            id: 'paypal_payment_method_' . bin2hex(random_bytes(8)),
            type: $paymentMethod->type,
            details: $paymentMethod->details,
            customerId: $paymentMethod->customerId,
            billingDetails: $paymentMethod->billingDetails,
            metadata: $paymentMethod->metadata,
        );
    }

    /**
     * PayPal does not expose a generic "PaymentMethod" resource compatible with this library's interface.
     *
     * This method returns a placeholder payment method with the provided ID.
     */
    public function retrievePaymentMethod(string $paymentMethodId): PaymentMethod
    {
        return new PaymentMethod(id: $paymentMethodId, type: 'paypal');
    }

    /**
     * PayPal does not expose a generic "PaymentMethod" resource compatible with this library's interface.
     *
     * This method returns the input payment method unchanged.
     */
    public function updatePaymentMethod(PaymentMethod $paymentMethod): PaymentMethod
    {
        return $paymentMethod;
    }

    /**
     * PayPal does not expose a generic "PaymentMethod" resource compatible with this library's interface.
     *
     * This method is a no-op.
     */
    public function detachPaymentMethod(string $paymentMethodId): void
    {
        // Intentionally no-op.
    }

    /**
     * PayPal does not expose a generic "PaymentMethod" attachment API compatible with this library's interface.
     *
     * This method returns the payment method unchanged.
     */
    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod
    {
        return $this->retrievePaymentMethod($paymentMethodId);
    }

    /**
     * Creates a refund.
     *
     * PayPal refunds are created against a **capture ID** (not an order ID).
     * To use this method you must pass a capture ID either:
     * - as $paymentIntentId directly; OR
     * - as ['capture_id' => '...'] in $params
     */
    public function createRefund(string $paymentIntentId, int $amount = null, array $params = []): array
    {
        $captureId = (string) ($params['capture_id'] ?? $paymentIntentId);

        $data = [];
        if ($amount !== null) {
            $data['amount'] = [
                'value' => self::formatAmount($amount),
                'currency_code' => strtoupper($params['currency'] ?? 'USD'),
            ];
        }

        if (isset($params['note_to_payer'])) {
            $data['note_to_payer'] = (string) $params['note_to_payer'];
        }

        return $this->sendRequest(
            $this->createRequest('POST', "/v2/payments/captures/{$captureId}/refund", $data)
        );
    }

    /**
     * Adds authorization and request id headers.
     */
    protected function createRequest(string $method, string $endpoint, array $data = null): \Psr\Http\Message\RequestInterface
    {
        $request = parent::createRequest($method, $endpoint, $data);
        $uri = (string) $request->getUri();

        // Do not add Bearer auth to token request.
        if (!str_contains($uri, '/v1/oauth2/token')) {
            $request = $request
                ->withHeader('Authorization', 'Bearer ' . $this->getAccessToken())
                ->withHeader('PayPal-Request-Id', self::uuidV4());
        }

        return $request;
    }

    /**
     * PayPal error responses are different from Stripe/YooKassa.
     * Convert the response to {@see PaymentException} format.
     *
     * @throws PaymentException
     */
    protected function handleErrorResponse(array $responseData, int $statusCode): void
    {
        $message = $responseData['message']
            ?? $responseData['error_description']
            ?? 'PayPal request failed.';

        $errorCode = $responseData['name'] ?? $responseData['error'] ?? null;

        $param = null;
        if (!empty($responseData['details']) && is_array($responseData['details'])) {
            $first = $responseData['details'][0] ?? null;
            if (is_array($first) && isset($first['field'])) {
                $param = (string) $first['field'];
            }
        }

        throw new PaymentException(
            $message,
            $errorCode,
            'paypal',
            null,
            $param,
            $statusCode
        );
    }

    private function getAccessToken(): string
    {
        $now = time();

        if ($this->accessToken !== null && $this->accessTokenExpiresAt > ($now + 30)) {
            return $this->accessToken;
        }

        $request = $this->requestFactory->createRequest('POST', $this->getBaseUri() . '/v1/oauth2/token')
            ->withHeader('Authorization', 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $request = $request->withBody(
            $this->streamFactory->createStream('grant_type=client_credentials')
        );

        $response = $this->httpClient->sendRequest($request);
        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data) || empty($data['access_token'])) {
            throw new PaymentException(
                'Failed to obtain PayPal access token.',
                'paypal_auth_error',
                'paypal',
                null,
                null,
                $response->getStatusCode()
            );
        }

        $this->accessToken = (string) $data['access_token'];
        $expiresIn = (int) ($data['expires_in'] ?? 300);
        $this->accessTokenExpiresAt = $now + $expiresIn;

        return $this->accessToken;
    }

    private function mapOrderToPaymentIntent(array $order, ?PaymentIntent $original): PaymentIntent
    {
        $purchaseUnit = $order['purchase_units'][0] ?? [];

        $amountValue = $purchaseUnit['amount']['value'] ?? null;
        $currency = $purchaseUnit['amount']['currency_code'] ?? ($original?->currency ?? 'USD');

        $amountMinor = $original?->amount ?? null;
        if ($amountMinor === null && $amountValue !== null) {
            $amountMinor = self::parseAmount((string) $amountValue);
        }

        $nextAction = null;
        $approve = self::findLink($order['links'] ?? [], ['approve', 'payer-action']);
        if ($approve !== null) {
            $nextAction = [
                'type' => 'redirect_to_url',
                'redirect_to_url' => [
                    'url' => $approve,
                ],
            ];
        }

        $metadata = $original?->metadata ?? [];
        // Preserve PayPal order data for consumers who need to extract capture / authorization IDs.
        $metadata['paypal_order'] = $order;

        // Surface custom_id (if it was provided).
        if (isset($purchaseUnit['custom_id'])) {
            $metadata['order_id'] = (string) $purchaseUnit['custom_id'];
        }

        return PaymentIntent::fromArray([
            'id' => (string) ($order['id'] ?? $original?->id ?? ''),
            'amount' => $amountMinor ?? 0,
            'currency' => strtoupper((string) $currency),
            'status' => (string) ($order['status'] ?? $original?->status ?? 'UNKNOWN'),
            'customer_id' => $original?->customerId,
            'payment_method_id' => $original?->paymentMethodId ?? 'paypal',
            'metadata' => $metadata,
            'description' => $original?->description,
            'receipt_email' => $original?->receiptEmail,
            'next_action' => $nextAction,
            'capture_method' => $original?->captureMethod ?? false,
        ]);
    }

    private static function findLink(array $links, array $rels): ?string
    {
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $rel = $link['rel'] ?? null;
            if ($rel !== null && in_array($rel, $rels, true) && isset($link['href'])) {
                return (string) $link['href'];
            }
        }
        return null;
    }

    private static function formatAmount(int $amountMinor): string
    {
        // Assume 2 decimals. This matches typical USD/EUR/etc flows and is consistent with existing tests.
        return number_format($amountMinor / 100, 2, '.', '');
    }

    private static function parseAmount(string $amount): int
    {
        // Assume 2 decimals.
        return (int) round(((float) $amount) * 100);
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20)
        );
    }

    private function extractCaptureId(array $captureResponse): ?string
    {
        $pu = $captureResponse['purchase_units'][0] ?? null;
        $captures = $pu['payments']['captures'] ?? null;
        if (is_array($captures) && isset($captures[0]['id'])) {
            return (string) $captures[0]['id'];
        }

        if (isset($captureResponse['id']) && is_string($captureResponse['id'])) {
            return $captureResponse['id'];
        }

        return null;
    }

    private function extractAuthorizationId(array $authorizeResponse): ?string
    {
        $pu = $authorizeResponse['purchase_units'][0] ?? null;
        $auths = $pu['payments']['authorizations'] ?? null;
        if (is_array($auths) && isset($auths[0]['id'])) {
            return (string) $auths[0]['id'];
        }

        return null;
    }
}
