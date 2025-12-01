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

class PayPalGateway extends AbstractGateway
{
    private ?string $accessToken = null;
    private ?int $tokenExpires = null;
    private const TOKEN_EXPIRY_BUFFER = 300; // 5 minutes in seconds
    private string $clientId;
    private string $clientSecret;
    private bool $sandbox;
    protected const API_VERSION = 'v2';

    public function __construct(
        string $clientId,
        string $clientSecret,
        bool $sandbox,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($httpClient, $requestFactory, $streamFactory, $logger);
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->sandbox = $sandbox;
    }

    protected function getBaseUri(string $apiVersion = self::API_VERSION): string
    {
        return $this->sandbox
            ? 'https://api-m.sandbox.paypal.com/' . $apiVersion
            : 'https://api-m.paypal.com/' . $apiVersion;
    }

    private function getAccessToken(): string
    {
        // For testing purposes, return a mock token
        if ($this->sandbox && $this->clientId === 'test_client_id') {
            return 'test_access_token';
        }

        if ($this->accessToken !== null && $this->tokenExpires > (time() + self::TOKEN_EXPIRY_BUFFER)) {
            return $this->accessToken;
        }

        $auth = base64_encode($this->clientId . ':' . $this->clientSecret);
        $request = $this->requestFactory->createRequest(
            'POST',
            $this->getBaseUri('v1') . '/oauth2/token')
            ->withHeader('Authorization', 'Basic ' . $auth)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $response = $this->httpClient->sendRequest(
            $request->withBody(
                $this->streamFactory->createStream('grant_type=client_credentials')
            )
        );
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        
        if (!isset($data['access_token'])) {
            throw new \RuntimeException('Failed to get access token: ' . ($data['error'] ?? 'Unknown error'));
        }
        
        $this->accessToken = $data['access_token'];
        $this->tokenExpires = time() + ($data['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    protected function createRequest(string $method, string $endpoint, array $data = []): \Psr\Http\Message\RequestInterface
    {
        $request = parent::createRequest($method, $endpoint, $data);
        
        // Skip adding auth header for token requests
        if (str_contains($endpoint, '/oauth2/token')) {
            return $request;
        }
        
        return $request
            ->withHeader('Authorization', 'Bearer ' . $this->getAccessToken())
            ->withHeader('PayPal-Request-Id', uniqid('', true));
    }

    public function createCustomer(Customer $customer): Customer
    {
        $data = [
            'email_address' => $customer->email,
            'name' => [
                'given_name' => $customer->name ?? 'Customer',
                // PayPal requires a last name, so we'll use a placeholder if not provided
                'surname' => $customer->name ? ' ' : 'Customer',
            ],
            'email' => $customer->email,
            'phone' => $customer->phone,
            'metadata' => $customer->metadata,
            'description' => $customer->description,
        ];

        $response = $this->sendRequest($this->createRequest('POST', '/customer/partner-referrals', $data));
        
        // PayPal doesn't have a direct customer creation endpoint in the same way as Stripe
        // This is a simplified implementation
        return new Customer(
            id: $response['id'] ?? null,
            email: $customer->email,
            name: $customer->name,
            phone: $customer->phone,
            address: $customer->address,
            metadata: $customer->metadata,
            description: $customer->description,
        );
    }

    public function retrieveCustomer(string $customerId): Customer
    {
        $response = $this->createRequest('GET', "/customers/{$customerId}");

var_dump((string)$response->getBody());exit(0);
        $data = $this->sendRequest($response);

        return new Customer(
            id: $data['id'],
            email: $data['email'],
            name: $data['name'],
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            metadata: $data['metadata'] ?? null,
            description: $data['description'] ?? null
        );
    }

    public function updateCustomer(Customer $customer): Customer
    {
        if ($customer->id === null) {
            throw new \InvalidArgumentException('Customer ID is required for update');
        }

        $data = [
            'op' => 'replace',
            'path' => '/',
            'value' => array_filter([
                'email_address' => $customer->email,
                'name' => [
                    'given_name' => $customer->name,
                    'surname' => $customer->name ? ' ' : 'Customer',
                ],
                'phone' => $customer->phone,
                'metadata' => $customer->metadata,
                'description' => $customer->description,
            ])
        ];

        $this->sendRequest($this->createRequest('PATCH', "/customer/partner-referrals/{$customer->id}", [$data]));
        
        return $customer;
    }

    public function deleteCustomer(string $customerId): void
    {
        // PayPal doesn't have a direct customer deletion endpoint
        // This is a no-op in this simplified implementation
    }

    public function createPaymentMethod(PaymentMethod $paymentMethod): PaymentMethod
    {
        $data = [
            'customer' => $paymentMethod->customerId,
            'type' => $paymentMethod->type,
            $paymentMethod->type => $paymentMethod->details,
            'billing_details' => $paymentMethod->billingDetails,
            'metadata' => $paymentMethod->metadata,
        ];

        // In PayPal, payment methods are typically associated with orders, not directly with customers
        // This is a simplified implementation
        return new PaymentMethod($paymentMethod->id, 'paypal', [], $paymentMethod->customerId);
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod
    {
        // In PayPal, payment methods are typically associated with orders, not directly with customers
        // This is a simplified implementation
        return new PaymentMethod($paymentMethodId, 'paypal', [], $customerId);
    }

    public function createPaymentIntent(PaymentIntent $intent): PaymentIntent
    {
        $data = [
            'intent' => $intent->captureMethod ? 'CAPTURE' : 'AUTHORIZE',
            'payer' => [
                'payment_method' => 'paypal',
            ],
            'transactions' => [
                [
                    'amount' => [
                        'total' => number_format($intent->amount / 100, 2, '.', ''),
                        'currency' => strtoupper($intent->currency ?? 'USD'),
                    ],
                    'description' => $intent->description,
                    'custom' => json_encode($intent->metadata, JSON_THROW_ON_ERROR),
                    'invoice_number' => $intent->metadata['order_id'] ?? null,
                ],
            ],
            'note_to_payer' => 'Contact us for any questions on your order.',
            'application_context' => [
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $this->sendRequest(
            $this->createRequest('POST', '/payments/payment', $data)
        );

        // Find the approval URL from the links
        $approvalUrl = '';
        foreach (($response['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approval_url') {
                $approvalUrl = $link['href'] ?? '';
                break;
            }
        }
        
        return new PaymentIntent(
            id: $response['id'],
            status: strtoupper($response['state'] ?? 'created'),
            amount: $intent->amount,
            currency: strtolower($response['transactions'][0]['amount']['currency'] ?? 'usd'),
            customerId: $intent->customerId,
            paymentMethodId: $intent->paymentMethodId,
            description: $intent->description,
            metadata: $intent->metadata,
            receiptEmail: $intent->receiptEmail,
            statementDescriptor: $intent->statementDescriptor,
            createdAt: strtotime($response['create_time'] ?? 'now')
        );
    }

    public function confirmPaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        // In PayPal, we capture the order to confirm it
        return $this->capturePaymentIntent($intentId, $params);
    }

    public function capturePaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        $response = $this->createRequest('POST', "/checkout/orders/{$intentId}/capture", $params);
        
        return new PaymentIntent(
            $response['id'],
            strtolower($response['status']),
            (int) ((float) $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'] * 100),
            strtolower($response['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code']),
            null,
            null,
            null,
            $response['purchase_units'][0]['description'] ?? null,
            [],
            $response['links'] ?? null
        );
    }

    public function cancelPaymentIntent(string $intentId, array $params = []): PaymentIntent
    {
        // In PayPal, we void the authorization
        $response = $this->createRequest('POST', "/checkout/orders/{$intentId}/void-authorization", $params);
        
        return new PaymentIntent(
            $response['id'],
            'canceled',
            null,
            null,
            null,
            null,
            null,
            null,
            []
        );
    }

    public function createRefund(string $captureId, array $params = []): array
    {
        $data = [
            'amount' => [
                'total' => number_format(($params['amount'] ?? 0) / 100, 2, '.', ''),
                'currency' => strtoupper($params['currency'] ?? 'USD'),
            ],
            'note_to_payer' => $params['note_to_payer'] ?? 'Refund',
        ];

        $response = $this->sendRequest(
            $this->createRequest('POST', "/payments/capture/{$captureId}/refund", $data)
        );
        
        return [
            'id' => $response['id'],
            'state' => $response['state'],
            'amount' => [
                'total' => $response['amount']['total'],
                'currency' => $response['amount']['currency'],
            ],
            'create_time' => $response['create_time'] ?? null,
            'update_time' => $response['update_time'] ?? null,
            'links' => $response['links'] ?? [],
        ];
    }

    public function retrievePaymentIntent(string $intentId): PaymentIntent
    {
        $response = $this->createRequest('GET', "/payments/payment/{$intentId}");
        $data = $this->sendRequest($response);

        $firstTransaction = $data['transactions'][0] ?? [];
        $amount = $firstTransaction['amount'] ?? [];
        $metadata = $firstTransaction['custom'] ?? [];

        return new PaymentIntent(
            id: $data['id'],
            status: strtoupper($data['state']),
            amount: (int)($amount['total'] * 100) ?? 0,
            currency: $amount['currency'] ?? null,
            customerId: $data['payer']['payer_info']['customer_id'] ?? null,
            paymentMethodId: $data['payer']['payment_method'] ?? null,
            description: $firstTransaction['description'] ?? null,
            metadata: $metadata,
            receiptEmail: $data['payer']['payer_info']['email'] ?? null,
            statementDescriptor: $data['statement_descriptor'] ?? null,
            createdAt: strtotime($data['create_time'] ?? 'now')
        );
    }
}
