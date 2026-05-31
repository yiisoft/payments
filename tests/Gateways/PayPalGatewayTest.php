<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Gateways\PayPalGateway;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;
use Yiisoft\Payments\Tests\Support\TestHttpClient;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalSignatureVerifier;
use Yiisoft\Payments\Webhooks\WebhookPayPalValidator;
use Nyholm\Psr7\Factory\Psr17Factory;

final class PayPalGatewayTest extends TestCase
{
    private PayPalGateway $gateway;
    private TestHttpClient $httpClient;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
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

    public function testCreatesRecommendedWebhookValidatorWithDefaultSignatureVerifier(): void
    {
        $this->httpClient->queueJsonResponse([
            'access_token' => 'test_access_token',
            'expires_in' => 3600,
        ]);
        $this->httpClient->queueJsonResponse([
            'verification_status' => 'SUCCESS',
        ]);

        $validator = $this->gateway->createWebhookValidator('WH-GATEWAY-123');

        $this->assertInstanceOf(WebhookPayPalValidator::class, $validator);

        $result = $validator->validate($this->paypalWebhookInput());

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
        $this->assertSame('POST', $this->httpClient->lastRequest['method']);
        $this->assertStringContainsString('/v1/notifications/verify-webhook-signature', $this->httpClient->lastRequest['uri']);

        $requestBody = json_decode($this->httpClient->lastRequest['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('WH-GATEWAY-123', $requestBody['webhook_id']);
        $this->assertSame('WH-EVENT-123', $requestBody['webhook_event']['id']);
    }

    public function testCreatesDefaultWebhookSignatureVerifier(): void
    {
        $this->assertInstanceOf(
            WebhookPayPalSignatureVerifier::class,
            $this->gateway->createWebhookSignatureVerifier(),
        );
    }

    public function testCreateCustomerIsLocalNoOp(): void
    {
        $customer = new Customer(
            id: null,
            email: 'test@example.com',
            name: 'Test User',
        );

        $created = $this->gateway->createCustomer($customer);

        $this->assertNotNull($created->id);
        $this->assertSame('test@example.com', $created->email);
        $this->assertSame('Test User', $created->name);
    }

    public function testCreatePaymentIntentCreatesOrderV2(): void
    {
        $this->queueToken();

        $this->httpClient->queueJsonResponse([
            'id' => 'ORDER-123',
            'intent' => 'CAPTURE',
            'status' => 'CREATED',
            'purchase_units' => [
                [
                    'amount' => ['currency_code' => 'USD', 'value' => '10.00'],
                    'custom_id' => '12345',
                ],
            ],
            'links' => [
                ['rel' => 'approve', 'href' => 'https://www.paypal.com/checkoutnow?token=ORDER-123'],
            ],
        ]);

        $paymentIntent = new PaymentIntent(
            id: null,
            amount: 1000,
            currency: 'USD',
            customerId: 'CUST-123',
            paymentMethodId: 'paypal',
            metadata: ['order_id' => '12345', 'return_url' => 'https://example.com/ok', 'cancel_url' => 'https://example.com/cancel'],
            receiptEmail: 'test@example.com',
            captureMethod: false
        );

        $result = $this->gateway->createPaymentIntent($paymentIntent);

        $this->assertSame('ORDER-123', $result->id);
        $this->assertSame('CREATED', $result->status);
        $this->assertSame(1000, $result->amount);
        $this->assertSame('USD', $result->currency);
        $this->assertSame('12345', $result->metadata['order_id']);
        $this->assertSame('https://www.paypal.com/checkoutnow?token=ORDER-123', $result->nextAction['redirect_to_url']['url']);

        $lastRequest = $this->httpClient->lastRequest;
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('/v2/checkout/orders', $lastRequest['uri']);
        $this->assertArrayHasKey('Authorization', $lastRequest['headers']);
        $this->assertStringContainsString('Bearer test_access_token', $lastRequest['headers']['Authorization'][0]);

        $body = json_decode($lastRequest['body'], true);
        $this->assertSame('CAPTURE', $body['intent']);
        $this->assertSame('10.00', $body['purchase_units'][0]['amount']['value']);
    }

    public function testCreatePaymentMethodIsLocalNoOp(): void
    {
        $paymentMethod = new PaymentMethod(
            id: null,
            type: 'paypal',
            details: ['email' => 'test@example.com'],
            customerId: 'CUST-123',
        );

        $created = $this->gateway->createPaymentMethod($paymentMethod);

        $this->assertNotNull($created->id);
        $this->assertSame('paypal', $created->type);
        $this->assertSame('CUST-123', $created->customerId);
    }

    public function testCreateRefundUsesCaptureRefundV2(): void
    {
        $this->queueToken();

        $this->httpClient->queueJsonResponse([
            'id' => 'RFD-123',
            'status' => 'COMPLETED',
            'amount' => ['value' => '10.00', 'currency_code' => 'USD'],
        ]);

        $result = $this->gateway->createRefund(
            paymentIntentId: 'CAPTURE-123',
            params: ['amount' => 1000]
        );

        $this->assertSame('RFD-123', $result['id']);
        $this->assertSame('COMPLETED', $result['status']);

        $lastRequest = $this->httpClient->lastRequest;
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('/v2/payments/captures/CAPTURE-123/refund', $lastRequest['uri']);
    }

    private function paypalWebhookInput(): WebhookInput
    {
        return new WebhookInput(
            rawBody: json_encode([
                'id' => 'WH-EVENT-123',
                'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
                'resource' => [
                    'id' => 'CAPTURE-123',
                    'status' => 'COMPLETED',
                ],
            ], JSON_THROW_ON_ERROR),
            headers: [
                'PayPal-Auth-Algo' => ['SHA256withRSA'],
                'PayPal-Cert-Url' => ['https://api-m.paypal.com/certs/test.pem'],
                'PayPal-Transmission-Id' => ['transmission-id'],
                'PayPal-Transmission-Sig' => ['signature'],
                'PayPal-Transmission-Time' => ['2026-04-29T10:00:00Z'],
            ],
            providerId: 'paypal',
        );
    }

    private function queueToken(): void
    {
        $this->httpClient->queueJsonResponse([
            'access_token' => 'test_access_token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);
    }
}
