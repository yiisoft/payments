<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Gateways;

use Exception;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Payments\Endpoints\YooKassaEndpoints;

class YooKassaGateway extends AbstractGateway
{
    private YooKassaEndpoints $endpoints;

    public function __construct(
        private string $shopId,
        private string $secretKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null,
        ?YooKassaEndpoints $endpoints = null
    ) {
        parent::__construct($httpClient, $requestFactory, $streamFactory, $logger);
        $this->endpoints = $endpoints ?? new YooKassaEndpoints();
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

    public function createCustomer(Customer $customer): Customer
    {
        return $customer;
    }

    public function retrieveCustomer(string $customerId): Customer
    {
        throw new \RuntimeException('YooKassa API does not support retrieving customer');
    }

    public function updateCustomer(Customer $customer): Customer
    {
        throw new \RuntimeException('YooKassa API does not support updating customer');
    }

    public function deleteCustomer(string $customerId): void
    {
        throw new \RuntimeException('YooKassa API does not support delete customer');
    }

    public function createPaymentMethod(PaymentMethod $paymentMethod): PaymentMethod
    {
        return $paymentMethod;
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod
    {
        return new PaymentMethod($paymentMethodId, 'yookassa', [], $customerId);
    }

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

    public function retrievePaymentIntent(string $intentId): PaymentIntent
    {
        $request = $this->createRequest('GET', "/payments/{$intentId}");
        $response = $this->sendRequest($request);

        return $this->parsePaymentIntent($response);
    }

    public function confirmPaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        return $this->capturePaymentIntent($intentId,  $params);
    }

    public function capturePaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $request = $this->createRequest('POST', "/payments/{$intentId}/capture", []);
        $response = $this->sendRequest($request);

        return $this->parsePaymentIntent($response);
    }

    public function cancelPaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $request = $this->createRequest('POST', "/payments/{$intentId}/cancel", $params);
        $response = $this->sendRequest($request);

        return $this->parsePaymentIntent($response);
    }

    public function createRefund(string $paymentId, array $params = []): array
    {
        $data = [
            'payment_id' => $paymentId,
            'amount' => $this->createAmountValue($params['amount'], $params['currency']),
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
