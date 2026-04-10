<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Gateways;

use Exception;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;
use Yiisoft\Payments\Exceptions\PaymentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Payments\Endpoints\YooKassaEndpoints;

class YooKassaGateway extends AbstractGateway
{
    public function __construct(
        private string $shopId,
        private string $secretKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null,
        private ?YooKassaEndpoints $endpoints = new YooKassaEndpoints()
    ) {
        parent::__construct($httpClient, $requestFactory, $streamFactory, $logger);
    }

    protected function getBaseUri(): string
    {
        return $this->endpoints->baseUri;
    }

    protected function createRequest(string $method, string $endpoint, array $data = [])
    {
        $request = parent::createRequest($method, $endpoint, $data);

        $request = $request
            ->withHeader('Authorization', $this->createAuthorizationHeader())
            ->withHeader('Idempotence-Key', $this->createIdempotenceKey());

        return $request;
    }

    /**
     * @sandbox-support not_implemented
     * @sandbox-reason YooKassa public API does not expose a standalone customer resource or customer CRUD endpoints compatible with this interface. This operation cannot be implemented against the public YooKassa API.
     */
    public function createCustomer(Customer $customer): Customer
    {
        return $customer;
    }

    /**
     * @sandbox-support not_implemented
     * @sandbox-reason YooKassa public API does not expose a standalone customer resource or customer CRUD endpoints compatible with this interface. This operation cannot be implemented against the public YooKassa API.
     */
    public function retrieveCustomer(string $customerId): Customer
    {
        throw new PaymentException('YooKassa API does not support retrieving customer');
    }

    /**
     * @sandbox-support not_implemented
     * @sandbox-reason YooKassa public API does not expose a standalone customer resource or customer CRUD endpoints compatible with this interface. This operation cannot be implemented against the public YooKassa API.
     */
    public function updateCustomer(Customer $customer): Customer
    {
        throw new PaymentException('YooKassa API does not support updating customer');
    }

    /**
     * @sandbox-support not_implemented
     * @sandbox-reason YooKassa public API does not expose a standalone customer resource or customer CRUD endpoints compatible with this interface. This operation cannot be implemented against the public YooKassa API.
     */
    public function deleteCustomer(string $customerId): void
    {
        throw new PaymentException('YooKassa API does not support delete customer');
    }

    /**
     * @sandbox-support not_implemented
     * @sandbox-reason YooKassa public API does not expose a standalone generic payment-method resource compatible with this interface. This operation cannot be implemented against the public YooKassa API.
     */
    public function createPaymentMethod(PaymentMethod $paymentMethod): PaymentMethod
    {
        return $paymentMethod;
    }

    /**
     * @sandbox-support not_implemented
     * @sandbox-reason YooKassa public API does not expose a generic payment-method attachment endpoint compatible with this interface. This operation cannot be implemented against the public YooKassa API.
     */
    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod
    {
        return new PaymentMethod($paymentMethodId, 'yookassa', [], $customerId);
    }

    /**
     * @sandbox-support implemented
     */
    public function createPaymentIntent(PaymentIntent $intent): PaymentIntent
    {
        $data = [
            'amount' => [
                'value' => number_format($intent->amount / 100, 2, '.', ''),
                'currency' => strtoupper($intent->currency ?? 'USD'),
            ],
            'capture' => false,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $intent->metadata['return_url'] ?? ''
            ],
            'description' => $intent->description,
            'metadata' => $intent->metadata,
        ];

        if ($intent->paymentMethodId !== null) {
            $data['payment_method_data'] = [
                'type' => $intent->paymentMethodId,
            ];
        }

        $request = $this->createRequest('POST', '/payments', $data);
        $response = $this->sendRequest($request);

        return $this->parsePaymentIntent($response);
    }

    /**
     * @sandbox-support implemented
     */
    public function retrievePaymentIntent(string $intentId): PaymentIntent
    {
        $request = $this->createRequest('GET', "/payments/{$intentId}");
        $response = $this->sendRequest($request);

        return $this->parsePaymentIntent($response);
    }

    /**
     * @sandbox-support partial
     * @sandbox-reason YooKassa payment flow does not expose a separate generic confirm endpoint compatible with this interface; confirmation is handled by the provider flow and subsequent capture step.
     */
    public function confirmPaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        return $this->capturePaymentIntent($intentId,  $params);
    }

    /**
     * @sandbox-support implemented
     */
    public function capturePaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $request = $this->createRequest('POST', "/payments/{$intentId}/capture", []);
        $response = $this->sendRequest($request);

        return $this->parsePaymentIntent($response);
    }

    /**
     * @sandbox-support implemented
     */
    public function cancelPaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $request = $this->createRequest('POST', "/payments/{$intentId}/cancel", $params);
        $response = $this->sendRequest($request);

        return $this->parsePaymentIntent($response);
    }

    /**
     * @sandbox-support implemented
     */
    public function createRefund(string $paymentId, array $params = []): array
    {
        if (!array_key_exists('amount', $params) || !array_key_exists('currency', $params)) {
            throw new PaymentException('Refund "amount" and "currency" parameters are required.');
        }

        $amount = $params['amount'];
        $currency = $params['currency'];
        if (!is_int($amount) || $amount <= 0) {
            throw new PaymentException('Refund "amount" must be a positive integer representing minor currency units.');
        }
        if (!is_string($currency) || $currency === '') {
            throw new PaymentException('Refund "currency" must be a non-empty string.');
        }
        $data = [
            'payment_id' => $paymentId,
            'amount' => $this->createAmountValue($amount, $currency),
            'description' => $params['description'] ?? null,
        ];

        $response = $this->sendRequest($this->createRequest('POST', '/refunds', $data));
        $amount = $this->parseAmountValue($response);

        return [
            'id' => $response['id'],
            'payment_id' => $response['payment_id'],
            'status' => $response['status'],
            'created_at' => $response['created_at'],
            'amount' => $amount['value'],
            'currency' => $amount['currency']
        ];
    }

    protected function createIdempotenceKey()
    {
        return uniqid('yk_', true);
    }

    protected function createAuthorizationHeader()
    {
        return "Basic " . base64_encode("{$this->shopId}:{$this->secretKey}");
    }

    protected function createAmountValue(int $amount, string $currency)
    {
        return [
            'value' => number_format($amount / 100, 2, '.', ''),
            'currency' => strtoupper($currency),
        ];
    }

    protected function parseAmountValue(array $response)
    {
        return [
            'value' => (int) ($response['amount']['value'] * 100),
            'currency' => strtolower($response['amount']['currency']),
        ];
    }

    protected function parsePaymentIntent(array $response): PaymentIntent
    {
        $amount = $this->parseAmountValue($response);

        return new PaymentIntent(
            id: $response['id'],
            status: $response['status'],
            amount: $amount['value'],
            currency: $amount['currency'],
            paymentMethodId: $response['payment_method']['id'] ?? null,
            description: $response['description'] ?? null,
            createdAt: strtotime($response['created_at']),
            metadata: [
                'confirmation_url' => $response['confirmation']['confirmation_url'] ?? null
            ]
        );
    }
}
