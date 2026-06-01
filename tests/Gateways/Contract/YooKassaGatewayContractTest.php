<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways\Contract;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use Yiisoft\Payments\Gateways\YooKassaGateway;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\PaymentGatewayInterface;
use Yiisoft\Payments\Tests\Support\TestHttpClient;

final class YooKassaGatewayContractTest extends GatewayContractTestCase
{
    protected function createGateway(TestHttpClient $http, Psr17Factory $factory): PaymentGatewayInterface
    {
        return new YooKassaGateway('shop_id', 'secret_key', $http, $factory, $factory, new NullLogger());
    }

    protected function givenCreatePaymentIntent(): PaymentIntent
    {
        $this->http->queueJsonResponse([
            'id' => '30ae77b9-000f-5001-8000-13e0de458932',
            'status' => 'pending',
            'amount' => ['value' => '100.00', 'currency' => 'RUB'],
            'description' => 'Test payment',
            'payment_method' => ['type' => 'bank_card', 'id' => 'pm_test123'],
            'created_at' => '2025-11-18T12:18:01.563Z',
            'confirmation' => [
                'type' => 'redirect',
                'confirmation_url' => 'https://yoomoney.ru/checkout/payments/v2/contract?orderId=30ae77b9',
            ],
            'metadata' => [],
        ]);

        return new PaymentIntent(
            amount: 1000,
            currency: 'rub',
            paymentMethodId: 'bank_card',
            metadata: ['return_url' => 'https://example.com/return'],
        );
    }

    protected function expectedCreatedIntent(): IntentExpectation
    {
        return new IntentExpectation('30ae77b9-000f-5001-8000-13e0de458932', 10000, 'RUB', 'pending');
    }

    protected function givenRetrievePaymentIntent(): string
    {
        $this->http->queueJsonResponse([
            'id' => '30ae77b9-000f-5001-8000-13e0de458932',
            'status' => 'succeeded',
            'amount' => ['value' => '100.00', 'currency' => 'RUB'],
            'description' => 'Test payment',
            'payment_method' => ['type' => 'bank_card', 'id' => 'pm_test123'],
            'created_at' => '2025-11-19T03:44:43.200Z',
        ]);

        return '30ae77b9-000f-5001-8000-13e0de458932';
    }

    protected function expectedRetrievedId(): string
    {
        return '30ae77b9-000f-5001-8000-13e0de458932';
    }

    protected function expectedRetrievedStatus(): string
    {
        return 'succeeded';
    }

    protected function givenCreateRefund(): string
    {
        $this->http->queueJsonResponse([
            'id' => '30af6093-0015-5001-8000-196e1cbaceef',
            'payment_id' => '30af50eb-000f-5001-8000-1533ca71a452',
            'status' => 'succeeded',
            'created_at' => '2025-11-19T04:51:31.067Z',
            'amount' => ['value' => '100.00', 'currency' => 'RUB'],
        ]);

        return '30af50eb-000f-5001-8000-1533ca71a452';
    }

    protected function refundParams(): array
    {
        return ['amount' => 1000, 'currency' => 'rub'];
    }

    protected function assertRefundShape(array $refund): void
    {
        $this->assertSame('30af6093-0015-5001-8000-196e1cbaceef', $refund['id']);
        $this->assertSame('30af50eb-000f-5001-8000-1533ca71a452', $refund['payment_id']);
        $this->assertSame(10000, $refund['amount']);
        $this->assertSame('rub', $refund['currency']);
        $this->assertSame('succeeded', $refund['status']);
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
}
