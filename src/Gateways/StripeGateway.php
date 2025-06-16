<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Gateways;

use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class StripeGateway extends AbstractGateway
{
    private string $apiVersion = '2023-10-16';

    public function __construct(
        private string $apiKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($httpClient, $requestFactory, $streamFactory, $logger);
    }

    protected function getBaseUri(): string
    {
        return 'https://api.stripe.com/v1';
    }

    /**
     * @return \Psr\Http\Message\RequestInterface
     */
    protected function createRequest(string $method, string $endpoint, array $data = [])
    {
        $request = parent::createRequest($method, $endpoint, $data);
        
        return $request
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Stripe-Version', $this->apiVersion);
    }

    public function createCustomer(Customer $customer): Customer
    {
        $data = array_filter([
            'email' => $customer->email,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address ? [
                'line1' => $customer->address['line1'] ?? null,
                'city' => $customer->address['city'] ?? null,
                'postal_code' => $customer->address['postal_code'] ?? null,
                'country' => $customer->address['country'] ?? null,
            ] : null,
            'metadata' => $customer->metadata,
            'description' => $customer->description,
        ]);

        $request = $this->createRequest('POST', '/customers', $data);
        $response = $this->sendRequest($request);
        
        return new Customer(
            id: $response['id'],
            email: $response['email'],
            name: $response['name'],
            phone: $response['phone'] ?? null,
            address: $response['address'] ?? null,
            metadata: $response['metadata'] ?? null,
            description: $response['description'] ?? null,
        );
    }

    public function retrieveCustomer(string $customerId): Customer
    {
        $request = $this->createRequest('GET', "/customers/{$customerId}");
        $response = $this->sendRequest($request);
        
        return new Customer(
            id: $response['id'],
            email: $response['email'],
            name: $response['name'],
            phone: $response['phone'] ?? null,
            address: $response['address'] ?? null,
            metadata: $response['metadata'] ?? null,
            description: $response['description'] ?? null,
        );
    }

    public function updateCustomer(Customer $customer): Customer
    {
        if ($customer->id === null) {
            throw new \InvalidArgumentException('Customer ID is required for update');
        }

        $data = array_filter([
            'email' => $customer->email,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address ? [
                'line1' => $customer->address['line1'] ?? null,
                'city' => $customer->address['city'] ?? null,
                'postal_code' => $customer->address['postal_code'] ?? null,
                'country' => $customer->address['country'] ?? null,
            ] : null,
            'metadata' => $customer->metadata,
            'description' => $customer->description,
        ]);

        $request = $this->createRequest('POST', "/customers/{$customer->id}", $data);
        $response = $this->sendRequest($request);
        
        return new Customer(
            id: $response['id'],
            email: $response['email'],
            name: $response['name'],
            phone: $response['phone'] ?? null,
            address: $response['address'] ?? null,
            metadata: $response['metadata'] ?? null,
            description: $response['description'] ?? null,
        );
    }

    public function deleteCustomer(string $customerId): void
    {
        $request = $this->createRequest('DELETE', "/customers/{$customerId}");
        $this->sendRequest($request);
    }

    public function createPaymentMethod(PaymentMethod $paymentMethod): PaymentMethod
    {
        $data = [
            'type' => $paymentMethod->type,
            $paymentMethod->type => $paymentMethod->details,
            'billing_details' => $paymentMethod->billingDetails,
            'metadata' => $paymentMethod->metadata,
        ];

        $request = $this->createRequest('POST', '/payment_methods', $data);
        $response = $this->sendRequest($request);
        
        return PaymentMethod::fromArray($response);
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod
    {
        $request = $this->createRequest(
            'POST',
            "/payment_methods/{$paymentMethodId}/attach",
            ['customer' => $customerId]
        );
        
        $response = $this->sendRequest($request);
        return PaymentMethod::fromArray($response);
    }

    public function createPaymentIntent(PaymentIntent $intent): PaymentIntent
    {
        $data = array_filter([
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'customer' => $intent->customerId,
            'payment_method' => $intent->paymentMethodId,
            'description' => $intent->description,
            'metadata' => $intent->metadata,
            'confirm' => $intent->confirm,
            'off_session' => $intent->offSession,
            'receipt_email' => $intent->receiptEmail,
            'statement_descriptor' => $intent->statementDescriptor,
        ]);

        $request = $this->createRequest('POST', '/payment_intents', $data);
        $response = $this->sendRequest($request);
        
        return PaymentIntent::fromArray($response);
    }

    public function confirmPaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $request = $this->createRequest('POST', "/payment_intents/{$intentId}/confirm", $params);
        $response = $this->sendRequest($request);
        return PaymentIntent::fromArray($response);
    }

    public function capturePaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $request = $this->createRequest('POST', "/payment_intents/{$intentId}/capture", $params);
        $response = $this->sendRequest($request);
        return PaymentIntent::fromArray($response);
    }

    public function cancelPaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $request = $this->createRequest('POST', "/payment_intents/{$intentId}/cancel", $params);
        $response = $this->sendRequest($request);
        return PaymentIntent::fromArray($response);
    }

    public function createRefund(string $paymentIntentId, array $params = []): array
    {
        $params['payment_intent'] = $paymentIntentId;
        $request = $this->createRequest('POST', '/refunds', $params);
        $response = $this->sendRequest($request);
        
        return [
            'id' => $response['id'],
            'amount' => $response['amount'] ?? null,
            'currency' => $response['currency'] ?? null,
            'status' => $response['status'] ?? 'succeeded',
            'created' => $response['created'] ?? null,
        ];
    }

    public function retrievePaymentIntent(string $intentId): PaymentIntent
    {
        $request = $this->createRequest('GET', "/payment_intents/{$intentId}");
        $response = $this->sendRequest($request);
        
        return new PaymentIntent(
            id: $response['id'],
            amount: $response['amount'],
            currency: $response['currency'],
            customerId: $response['customer'],
            paymentMethodId: $response['payment_method'],
            description: $response['description'],
            metadata: $response['metadata'],
            status: $response['status'],
            createdAt: $response['created'],
        );
    }
}
