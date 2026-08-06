<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways\Contract;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\PaymentGatewayInterface;
use Yiisoft\Payments\Tests\Support\TestHttpClient;

abstract class GatewayContractTestCase extends TestCase
{
    protected TestHttpClient $http;
    private PaymentGatewayInterface $gateway;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->http = new TestHttpClient($factory);
        $this->gateway = $this->createGateway($this->http, $factory);
    }

    abstract protected function createGateway(TestHttpClient $http, Psr17Factory $factory): PaymentGatewayInterface;

    abstract protected function givenCreatePaymentIntent(): PaymentIntent;

    abstract protected function expectedCreatedIntent(): IntentExpectation;

    abstract protected function givenRetrievePaymentIntent(): string;

    abstract protected function expectedRetrievedId(): string;

    abstract protected function expectedRetrievedStatus(): string;

    abstract protected function givenCreateRefund(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function refundParams(): array;

    /**
     * @param array<string, mixed> $refund
     */
    abstract protected function assertRefundShape(array $refund): void;

    abstract protected function givenCreateCustomer(): Customer;

    protected function customerApiIsRemote(): bool
    {
        return false;
    }

    protected function expectedRemoteCustomerId(): string
    {
        return '';
    }

    public function testCreatePaymentIntentReturnsNormalizedIntent(): void
    {
        $intent = $this->givenCreatePaymentIntent();
        $expected = $this->expectedCreatedIntent();

        $result = $this->gateway->createPaymentIntent($intent);

        $this->assertSame($expected->id, $result->id);
        $this->assertSame($expected->amount, $result->amount);
        $this->assertSame($expected->currency, $result->currency);
        $this->assertSame($expected->status, $result->status);
    }

    public function testCreatePaymentIntentNormalizesCurrencyToUpperCaseIso(): void
    {
        $intent = $this->givenCreatePaymentIntent();

        $currency = $this->gateway->createPaymentIntent($intent)->currency;

        $this->assertNotNull($currency);
        $this->assertSame(strtoupper($currency), $currency);
        $this->assertSame(1, preg_match('/^[A-Z]{3}$/', $currency));
    }

    public function testRetrievePaymentIntentReturnsRequestedIntent(): void
    {
        $intentId = $this->givenRetrievePaymentIntent();

        $result = $this->gateway->retrievePaymentIntent($intentId);

        $this->assertSame($this->expectedRetrievedId(), $result->id);
        $this->assertSame($this->expectedRetrievedStatus(), $result->status);
    }

    public function testCreateRefundReturnsNonEmptyResult(): void
    {
        $paymentIntentId = $this->givenCreateRefund();

        $refund = $this->gateway->createRefund($paymentIntentId, $this->refundParams());

        $this->assertNotEmpty($refund);
        $this->assertRefundShape($refund);
    }

    public function testCreateCustomerPreservesIdentity(): void
    {
        $customer = $this->givenCreateCustomer();

        $result = $this->gateway->createCustomer($customer);

        $this->assertSame($customer->email, $result->email);
        $this->assertSame($customer->name, $result->name);
    }

    public function testCreateCustomerAssignsRemoteIdWhenSupported(): void
    {
        if (!$this->customerApiIsRemote()) {
            $this->markTestSkipped('Provider has no remote customer API; createCustomer is a local operation.');
        }

        $customer = $this->givenCreateCustomer();

        $result = $this->gateway->createCustomer($customer);

        $this->assertSame($this->expectedRemoteCustomerId(), $result->id);
    }
}
