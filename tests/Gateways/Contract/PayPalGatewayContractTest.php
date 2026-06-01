<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways\Contract;

use Nyholm\Psr7\Factory\Psr17Factory;
use Yiisoft\Payments\Gateways\PayPalGateway;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\PaymentGatewayInterface;
use Yiisoft\Payments\Tests\Support\TestHttpClient;

final class PayPalGatewayContractTest extends GatewayContractTestCase
{
    protected function createGateway(TestHttpClient $http, Psr17Factory $factory): PaymentGatewayInterface
    {
        return new PayPalGateway(
            clientId: 'test_client_id',
            clientSecret: 'test_client_secret',
            sandbox: true,
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }

    protected function givenCreatePaymentIntent(): PaymentIntent
    {
        $this->queueAccessToken();
        $this->http->queueJsonResponse([
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

        return new PaymentIntent(
            amount: 1000,
            currency: 'USD',
            metadata: ['order_id' => '12345'],
            captureMethod: false,
        );
    }

    protected function expectedCreatedIntent(): IntentExpectation
    {
        return new IntentExpectation('ORDER-123', 1000, 'USD', 'CREATED');
    }

    protected function givenRetrievePaymentIntent(): string
    {
        $this->queueAccessToken();
        $this->http->queueJsonResponse([
            'id' => 'ORDER-123',
            'status' => 'COMPLETED',
            'purchase_units' => [
                [
                    'amount' => ['currency_code' => 'USD', 'value' => '10.00'],
                ],
            ],
        ]);

        return 'ORDER-123';
    }

    protected function expectedRetrievedId(): string
    {
        return 'ORDER-123';
    }

    protected function expectedRetrievedStatus(): string
    {
        return 'COMPLETED';
    }

    protected function givenCreateRefund(): string
    {
        $this->queueAccessToken();
        $this->http->queueJsonResponse([
            'id' => 'RFD-123',
            'status' => 'COMPLETED',
            'amount' => ['value' => '10.00', 'currency_code' => 'USD'],
        ]);

        return 'CAPTURE-123';
    }

    protected function refundParams(): array
    {
        return ['amount' => 1000];
    }

    protected function assertRefundShape(array $refund): void
    {
        $this->assertSame('RFD-123', $refund['id']);
        $this->assertSame('COMPLETED', $refund['status']);
        $this->assertSame(['value' => '10.00', 'currency_code' => 'USD'], $refund['amount']);
    }

    protected function givenCreateCustomer(): Customer
    {
        return new Customer(email: 'buyer@example.com', name: 'Test Buyer');
    }

    protected function customerApiIsRemote(): bool
    {
        return false;
    }

    protected function expectedRemoteCustomerId(): string
    {
        return '';
    }

    private function queueAccessToken(): void
    {
        $this->http->queueJsonResponse([
            'access_token' => 'test_access_token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);
    }
}
