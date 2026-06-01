<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways\Contract;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use Yiisoft\Payments\Gateways\StripeGateway;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\PaymentGatewayInterface;
use Yiisoft\Payments\Tests\Support\TestHttpClient;

final class StripeGatewayContractTest extends GatewayContractTestCase
{
    protected function createGateway(TestHttpClient $http, Psr17Factory $factory): PaymentGatewayInterface
    {
        return new StripeGateway('test_api_key', $http, $factory, $factory, new NullLogger());
    }

    protected function givenCreatePaymentIntent(): PaymentIntent
    {
        $this->http->queueJsonResponse([
            'id' => 'pi_test123',
            'amount' => 1000,
            'currency' => 'usd',
            'status' => 'requires_confirmation',
            'client_secret' => 'pi_test123_secret',
        ]);

        return new PaymentIntent(amount: 1000, currency: 'usd');
    }

    protected function expectedCreatedIntent(): IntentExpectation
    {
        return new IntentExpectation('pi_test123', 1000, 'USD', 'requires_confirmation');
    }

    protected function givenRetrievePaymentIntent(): string
    {
        $this->http->queueJsonResponse([
            'id' => 'pi_test123',
            'amount' => 1000,
            'currency' => 'usd',
            'customer' => 'cus_test123',
            'payment_method' => 'pm_test123',
            'description' => 'Test payment',
            'metadata' => [],
            'status' => 'succeeded',
            'created' => 1700000000,
        ]);

        return 'pi_test123';
    }

    protected function expectedRetrievedId(): string
    {
        return 'pi_test123';
    }

    protected function expectedRetrievedStatus(): string
    {
        return 'succeeded';
    }

    protected function givenCreateRefund(): string
    {
        $this->http->queueJsonResponse([
            'id' => 're_test123',
            'amount' => 1000,
            'currency' => 'usd',
            'status' => 'succeeded',
            'payment_intent' => 'pi_test123',
        ]);

        return 'pi_test123';
    }

    protected function refundParams(): array
    {
        return ['amount' => 1000];
    }

    protected function assertRefundShape(array $refund): void
    {
        $this->assertSame('re_test123', $refund['id']);
        $this->assertSame(1000, $refund['amount']);
        $this->assertSame('usd', $refund['currency']);
        $this->assertSame('succeeded', $refund['status']);
    }

    protected function givenCreateCustomer(): Customer
    {
        $this->http->queueJsonResponse([
            'id' => 'cus_test123',
            'email' => 'buyer@example.com',
            'name' => 'Test Buyer',
        ]);

        return new Customer(email: 'buyer@example.com', name: 'Test Buyer');
    }

    protected function customerApiIsRemote(): bool
    {
        return true;
    }

    protected function expectedRemoteCustomerId(): string
    {
        return 'cus_test123';
    }
}
