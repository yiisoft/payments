<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways;

use Yiisoft\Payments\Enums\PaymentMethodType;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;
use Yiisoft\Payments\Gateways\PayPalGateway;
use Yiisoft\Payments\Tests\Support\TestHttpClient;
use PHPUnit\Framework\TestCase;

final class PayPalGatewayTest extends TestCase
{
    private PayPalGateway $gateway;
    private TestHttpClient $httpClient;

    protected function setUp(): void
    {
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $this->httpClient = new TestHttpClient($factory);
        $this->gateway = new PayPalGateway(
            clientId: 'test_client_id',
            clientSecret: 'test_client_secret',
            sandbox: true,
            httpClient: $this->httpClient,
            requestFactory: $factory,
            streamFactory: $factory
        );
    }

    private function mockTokenRequest(): void
    {
        $this->httpClient->setNextResponse([
            'access_token' => 'test_access_token',
            'expires_in' => 3600,
            'token_type' => 'Bearer'
        ]);
    }

    private function withResponse(array $response): void
    {
        $this->httpClient->setNextResponse($response);
    }

    private function getLastRequest(): array
    {
        return $this->httpClient->lastRequest;
    }

    public function testCreateCustomer(): void
    {
        $this->mockTokenRequest();

        $testCustomer = new Customer(
            id: null,
            email: 'test@example.com',
            name: 'Test User',
            phone: '+1234567890',
            address: [
                'line1' => '123 Test St',
                'city' => 'Test City',
                'postal_code' => '12345',
                'country' => 'US',
            ],
            metadata: ['test_meta' => 'value'],
            description: 'Test Customer'
        );

        $this->withResponse([
            'id' => 'CUST-123',
            'email_address' => 'test@example.com',
            'name' => [
                'given_name' => 'Test',
                'surname' => 'User',
            ],
            'email' => 'test@example.com',
            'phone' => [
                'phone_number' => ['national_number' => '1234567890']
            ],
            'metadata' => ['test_meta' => 'value'],
            'description' => 'Test Customer',
        ]);

        $result = $this->gateway->createCustomer($testCustomer);

        $this->assertSame('test@example.com', $result->email);
        $this->assertSame('Test User', $result->name);
        $this->assertSame('+1234567890', $result->phone);
        $this->assertSame('Test Customer', $result->description);

        $lastRequest = $this->getLastRequest();
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('/customer/partner-referrals', $lastRequest['uri']);
    }

    public function testCreatePaymentIntent(): void
    {
        $this->mockTokenRequest();

        $paymentIntent = new PaymentIntent(
            id: null,
            amount: 1000,
            currency: 'USD',
            customerId: 'CUST-123',
            paymentMethodId: 'paypal',
            description: 'Test payment',
            metadata: ['order_id' => '12345'],
            receiptEmail: 'test@example.com',
            statementDescriptor: 'TEST'
        );

        $this->withResponse([
            'id' => 'PAY-123',
            'state' => 'created',
            'transactions' => [
                [
                    'amount' => [
                        'total' => '10.00',
                        'currency' => 'USD',
                    ],
                    'description' => 'Test payment',
                    'custom' => ['order_id' => '12345'],
                ]
            ],
            'payer' => [
                'payment_method' => 'paypal',
                'payer_info' => [
                    'email' => 'test@example.com',
                    'customer_id' => 'CUST-123'
                ]
            ],
            'create_time' => '2023-01-01T00:00:00Z',
            'links' => [
                ['rel' => 'self', 'method' => 'GET', 'href' => 'https://api.paypal.com/v1/payments/payment/PAY-123'],
                ['rel' => 'approve', 'method' => 'REDIRECT', 'href' => 'https://www.paypal.com/checkoutnow?token=PAY-123']
            ]
        ]);

        $result = $this->gateway->createPaymentIntent($paymentIntent);

        $this->assertSame('CREATED', $result->status);
        $this->assertSame(1000, $result->amount);
        $this->assertSame('USD', $result->currency);
        $this->assertSame('CUST-123', $result->customerId);
        $this->assertSame('paypal', $result->paymentMethodId);
        $this->assertSame('12345', $result->metadata['order_id']);
        $this->assertSame('test@example.com', $result->receiptEmail);

        $lastRequest = $this->getLastRequest();
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('/payments/payment', $lastRequest['uri']);
    }

    public function testCreatePaymentMethod(): void
    {
        $this->mockTokenRequest();

        $paymentMethod = new PaymentMethod(
            id: null,
            type: PaymentMethodType::PayPal,
            details: ['email' => 'test@example.com'],
            customerId: 'CUST-123',
            billingDetails: [
                'name' => 'Test User',
                'email' => 'test@example.com'
            ],
            metadata: ['source' => 'test']
        );

        // For PayPal, we're just testing that the method returns a PaymentMethod with the same customer ID
        $result = $this->gateway->createPaymentMethod($paymentMethod);

        $this->assertSame(PaymentMethodType::PayPal, $result->type);
        $this->assertSame('CUST-123', $result->customerId);

        // Since PayPal handles payment methods differently, we don't expect an API call here
        // The method should just return the payment method with the customer ID set
    }

    public function testCreateRefund(): void
    {
        $this->mockTokenRequest();

        $this->withResponse([
            'id' => 'REF-123',
            'state' => 'completed',
            'amount' => [
                'total' => '10.00',
                'currency' => 'USD',
            ],
            'capture_id' => 'CAPTURE-123',
            'create_time' => '2023-01-01T00:00:00Z',
            'links' => [
                ['rel' => 'self', 'method' => 'GET', 'href' => 'https://api.paypal.com/v1/payments/refund/REF-123']
            ]
        ]);

        $result = $this->gateway->createRefund('CAPTURE-123', [
            'amount' => 1000,
            'currency' => 'USD',
            'note_to_payer' => 'Refund for order #12345'
        ]);

        $this->assertSame('REF-123', $result['id']);
        $this->assertSame('completed', $result['state']);
        $this->assertSame('10.00', $result['amount']['total']);
        $this->assertSame('USD', $result['amount']['currency']);

        $lastRequest = $this->getLastRequest();
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('/payments/capture/CAPTURE-123/refund', $lastRequest['uri']);
    }
}
