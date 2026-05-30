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
use Yiisoft\Payments\Endpoints\StripeEndpoints;
use Yiisoft\Payments\Webhooks\WebhookCapabilities;
use Yiisoft\Payments\Webhooks\WebhookCapabilitiesProviderInterface;
use Yiisoft\Payments\Webhooks\WebhookCapability;
use Yiisoft\Payments\Webhooks\WebhookEntityKind;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPaymentOutcomeRules;
use Yiisoft\Payments\Webhooks\WebhookSupportStatus;

class StripeGateway extends AbstractGateway implements WebhookCapabilitiesProviderInterface
{
    private string $apiVersion = '2023-10-16';

    public function __construct(
        private string $apiKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null,
        private ?StripeEndpoints $endpoints = new StripeEndpoints()
    ) {
        parent::__construct($httpClient, $requestFactory, $streamFactory, $logger);
    }

    protected function getBaseUri(): string
    {
        return $this->endpoints->baseUri;
    }

    /**
     * @return \Psr\Http\Message\RequestInterface
     */
    protected function createRequest(string $method, string $endpoint, array $data = [])
    {
        $uri = rtrim($this->getBaseUri(), '/') . '/' . ltrim($endpoint, '/');
        $request = $this->requestFactory->createRequest($method, $uri)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'PaymentGateway/' . self::API_VERSION)
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Stripe-Version', $this->apiVersion);

        if (!empty($data)) {
            $stream = $this->streamFactory->createStream($this->buildFormBody($data));
            $request = $request
                ->withBody($stream)
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildFormBody(array $data): string
    {
        $pairs = [];

        foreach ($this->flattenFormFields($data) as $key => $value) {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode('&', $pairs);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function flattenFormFields(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $field = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

            if (is_array($value)) {
                $result += $this->flattenFormFields($value, $field);
                continue;
            }

            if (is_bool($value)) {
                $result[$field] = $value ? 'true' : 'false';
                continue;
            }

            $result[$field] = (string) $value;
        }

        return $result;
    }

    /**
     * @sandbox-support implemented
     */
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

    /**
     * @sandbox-support implemented
     */
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

    /**
     * @sandbox-support implemented
     */
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

    /**
     * @sandbox-support implemented
     */
    public function deleteCustomer(string $customerId): void
    {
        $request = $this->createRequest('DELETE', "/customers/{$customerId}");
        $this->sendRequest($request);
    }

    /**
     * @sandbox-support implemented
     */
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

    /**
     * @sandbox-support implemented
     */
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

    /**
     * @sandbox-support implemented
     */
    public function createPaymentIntent(PaymentIntent $intent): PaymentIntent
    {
        $data = array_filter([
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'customer' => $intent->customerId,
            'payment_method' => $intent->paymentMethodId,
            'description' => $intent->description,
            'metadata' => $intent->metadata,
            'capture_method' => $intent->captureMethod === null
                ? null
                : ($intent->captureMethod ? 'manual' : 'automatic'),
            'confirm' => $intent->confirm,
            'off_session' => $intent->offSession,
            'receipt_email' => $intent->receiptEmail,
            'statement_descriptor' => $intent->statementDescriptor,
        ]);

        $request = $this->createRequest('POST', '/payment_intents', $data);
        $response = $this->sendRequest($request);
        
        return PaymentIntent::fromArray($response);
    }

    /**
     * @sandbox-support implemented
     */
    public function confirmPaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $request = $this->createRequest('POST', "/payment_intents/{$intentId}/confirm", $params);
        $response = $this->sendRequest($request);
        return PaymentIntent::fromArray($response);
    }

    /**
     * @sandbox-support implemented
     */
    public function capturePaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $request = $this->createRequest('POST', "/payment_intents/{$intentId}/capture", $params);
        $response = $this->sendRequest($request);
        return PaymentIntent::fromArray($response);
    }

    /**
     * @sandbox-support implemented
     */
    public function cancelPaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $request = $this->createRequest('POST', "/payment_intents/{$intentId}/cancel", $params);
        $response = $this->sendRequest($request);
        return PaymentIntent::fromArray($response);
    }

    /**
     * @sandbox-support implemented
     */
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

    /**
     * @sandbox-support implemented
     */
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

    public function getWebhookCapabilities(): WebhookCapabilities
    {
        return new WebhookCapabilities(...array_map(
            fn (WebhookEventType $eventType): WebhookCapability => new WebhookCapability(
                $eventType,
                WebhookEntityKind::Payment,
                WebhookPaymentOutcomeRules::shouldProcess($eventType)
                    ? WebhookSupportStatus::Supported
                    : WebhookSupportStatus::Unsupported,
            ),
            [
                ...WebhookPaymentOutcomeRules::processedPaymentOutcomes(),
                ...WebhookPaymentOutcomeRules::unsupportedPaymentOutcomes(),
            ],
        ));
    }
}
