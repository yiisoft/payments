<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;
use Yiisoft\Payments\Gateways\StripeGateway;
use Yiisoft\Payments\Tests\Support\TestHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;

class StripeGatewayTest extends TestCase
{
    private TestHttpClient $httpClient;
    private Psr17Factory $psr17Factory;
    private StripeGateway $gateway;

    protected function setUp(): void
    {
        $this->psr17Factory = new Psr17Factory();
        $this->httpClient = new TestHttpClient($this->psr17Factory);
        
        $this->gateway = new StripeGateway(
            'test_api_key',
            $this->httpClient,
            $this->psr17Factory,
            $this->psr17Factory,
            new NullLogger()
        );
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
        $testCustomer = new Customer(
            null,
            'test@example.com',
            'Test User',
            '+1234567890',
            [
                'line1' => '123 Test St',
                'city' => 'Test City',
                'postal_code' => '12345',
                'country' => 'US',
            ],
            ['test_meta' => 'value'],
            'Test Customer'
        );

        $this->withResponse([
            'id' => 'cus_test123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'phone' => '+1234567890',
            'address' => [
                'line1' => '123 Test St',
                'city' => 'Test City',
                'postal_code' => '12345',
                'country' => 'US',
            ],
            'metadata' => ['test_meta' => 'value'],
            'description' => 'Test Customer',
        ]);

        $result = $this->gateway->createCustomer($testCustomer);

        $this->assertSame('cus_test123', $result->id);
        $this->assertSame('test@example.com', $result->email);
        $this->assertSame('Test User', $result->name);
        $this->assertSame('+1234567890', $result->phone);
        $this->assertSame('Test Customer', $result->description);
        
        $lastRequest = $this->getLastRequest();
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('/customers', $lastRequest['uri']);
    }

    public function testCreatePaymentIntent(): void
    {
        $paymentIntent = new PaymentIntent(
            null,
            null,
            1000, // $10.00
            'usd',
            'cus_test123',
            'pm_test123',
            null,
            'Test payment',
            ['order_id' => '12345'],
            null,
            null,
            true,
            false,
            false,
            'test@example.com',
            'TEST'
        );

        $this->withResponse([
            'id' => 'pi_test123',
            'amount' => 1000,
            'currency' => 'usd',
            'customer' => 'cus_test123',
            'payment_method' => 'pm_test123',
            'status' => 'requires_confirmation',
            'client_secret' => 'pi_test123_secret',
            'description' => 'Test payment',
            'metadata' => ['order_id' => '12345'],
            'receipt_email' => 'test@example.com',
            'statement_descriptor' => 'TEST'
        ]);

        $result = $this->gateway->createPaymentIntent($paymentIntent);

        $this->assertSame('pi_test123', $result->id);
        $this->assertSame(1000, $result->amount);
        $this->assertSame('USD', $result->currency);
        $this->assertSame('Test payment', $result->description);
        $this->assertSame('test@example.com', $result->receiptEmail);
        $this->assertSame('TEST', $result->statementDescriptor);
        
        $lastRequest = $this->getLastRequest();
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('/payment_intents', $lastRequest['uri']);
    }

    public function testConfirmPaymentIntent(): void
    {
        $this->withResponse([
            'id' => 'pi_test123',
            'status' => 'succeeded',
            'amount' => 1000,
            'currency' => 'usd'
        ]);

        $result = $this->gateway->confirmPaymentIntent('pi_test123', ['return_url' => 'https://example.com/return']);

        $this->assertSame('pi_test123', $result->id);
        $this->assertSame('succeeded', $result->status);
        
        $lastRequest = $this->getLastRequest();
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('/payment_intents/pi_test123/confirm', $lastRequest['uri']);
        $this->assertStringContainsString('return_url', $lastRequest['body']);
    }

    public function testCreateRefund(): void
    {
        $this->withResponse([
            'id' => 're_test123',
            'amount' => 1000,
            'currency' => 'usd',
            'status' => 'succeeded',
            'payment_intent' => 'pi_test123'
        ]);

        $result = $this->gateway->createRefund('pi_test123', ['amount' => 1000]);

        $this->assertSame('re_test123', $result['id']);
        $this->assertSame(1000, $result['amount']);
        $this->assertSame('usd', $result['currency']);
        $this->assertSame('succeeded', $result['status']);
        
        $lastRequest = $this->getLastRequest();
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertSame('https://api.stripe.com/v1/refunds', $lastRequest['uri']);
        $this->assertJsonStringEqualsJsonString(
            '{"payment_intent":"pi_test123","amount":1000}',
            $lastRequest['body']
        );
    }
}
