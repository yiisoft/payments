<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways\Contract;

use Nyholm\Psr7\Factory\Psr17Factory;
use Yiisoft\Payments\Gateways\RobokassaGateway;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\PaymentGatewayInterface;
use Yiisoft\Payments\Tests\Support\TestHttpClient;

final class RobokassaGatewayContractTest extends GatewayContractTestCase
{
    protected function createGateway(TestHttpClient $http, Psr17Factory $factory): PaymentGatewayInterface
    {
        return new RobokassaGateway(
            merchantLogin: 'demo',
            password1: 'pass1',
            password2: 'pass2',
            password3: 'pass3',
            testMode: true,
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }

    protected function givenCreatePaymentIntent(): PaymentIntent
    {
        $this->http->queueJsonResponse([
            'InvoiceID' => 123,
            'InvoiceUrl' => 'https://auth.robokassa.ru/Merchant/Index.aspx?InvoiceID=123',
            'Status' => 'CREATED',
        ]);

        return new PaymentIntent(
            amount: 2500,
            currency: 'RUB',
            metadata: ['InvId' => 123, 'Description' => 'Test invoice'],
            captureMethod: false,
        );
    }

    protected function expectedCreatedIntent(): IntentExpectation
    {
        return new IntentExpectation('123', 2500, 'RUB', 'CREATED');
    }

    protected function givenRetrievePaymentIntent(): string
    {
        $xml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <OperationStateResponse>
          <Result>
            <Code>0</Code>
            <Description>OK</Description>
          </Result>
          <State>
            <Code>5</Code>
          </State>
          <Info>
            <OpKey>OP-123</OpKey>
          </Info>
        </OperationStateResponse>
        XML;

        $this->http->queueRawResponse($xml, 200, ['Content-Type' => ['text/xml']]);

        return '123';
    }

    protected function expectedRetrievedId(): string
    {
        return '123';
    }

    protected function expectedRetrievedStatus(): string
    {
        return 'SUCCEEDED';
    }

    protected function givenCreateRefund(): string
    {
        $this->http->queueJsonResponse([
            'success' => true,
            'requestId' => 'REQ-123',
            'message' => null,
        ]);

        return '123';
    }

    protected function refundParams(): array
    {
        return ['amount' => 1000, 'op_key' => 'OP-123'];
    }

    protected function assertRefundShape(array $refund): void
    {
        $this->assertArrayHasKey('requestId', $refund);
        $this->assertTrue($refund['success']);
        $this->assertSame('REQ-123', $refund['requestId']);
    }

    protected function givenCreateCustomer(): Customer
    {
        return new Customer(email: 'buyer@example.com', name: 'Test Buyer');
    }
}
