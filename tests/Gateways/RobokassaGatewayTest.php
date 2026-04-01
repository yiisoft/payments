<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Gateways\RobokassaGateway;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Tests\Support\TestHttpClient;

final class RobokassaGatewayTest extends TestCase
{
    private RobokassaGateway $gateway;
    private TestHttpClient $httpClient;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->httpClient = new TestHttpClient($factory);

        $this->gateway = new RobokassaGateway(
            merchantLogin: 'demo',
            password1: 'pass1',
            password2: 'pass2',
            password3: 'pass3',
            testMode: true,
            httpClient: $this->httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }

    public function testCreatePaymentIntentCreatesInvoice(): void
    {
        $this->httpClient->queueJsonResponse([
            'InvoiceID' => 123,
            'InvoiceUrl' => 'https://auth.robokassa.ru/Merchant/Index.aspx?InvoiceID=123',
            'Status' => 'CREATED',
        ]);

        $intent = new PaymentIntent(
            id: null,
            amount: 2500,
            currency: 'RUB',
            metadata: ['InvId' => 123, 'Description' => 'Test invoice'],
            captureMethod: false
        );

        $result = $this->gateway->createPaymentIntent($intent);

        $this->assertSame('123', $result->id);
        $this->assertSame(2500, $result->amount);
        $this->assertSame('RUB', $result->currency);
        $this->assertSame('CREATED', $result->status);
        $this->assertSame('https://auth.robokassa.ru/Merchant/Index.aspx?InvoiceID=123', $result->nextAction['redirect_to_url']['url']);

        $lastRequest = $this->httpClient->lastRequest;
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('InvoiceServiceWebApi/api/CreateInvoice', $lastRequest['uri']);
        $this->assertArrayHasKey('Content-Type', $lastRequest['headers']);
        $this->assertSame('text/plain', $lastRequest['headers']['Content-Type'][0]);

        $this->assertStringStartsWith('"', $lastRequest['body']);
        $this->assertStringEndsWith('"', $lastRequest['body']);
        // JWT has 3 dot-separated segments inside quoted request body.
        $this->assertCount(3, explode('.', trim($lastRequest['body'], '"')));
    }


    public function testCreatePaymentIntentIncludesApiErrorDetailsInException(): void
    {
        $this->httpClient->queueJsonResponse([
            'Error' => 'Invalid signature.',
            'ErrorCode' => 'INVALID_SIGNATURE',
        ], 400);

        $intent = new PaymentIntent(
            id: null,
            amount: 2500,
            currency: 'RUB',
            description: 'Test payment'
        );

        $this->expectException(\Yiisoft\Payments\Exceptions\InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid signature.');

        try {
            $this->gateway->createPaymentIntent($intent);
        } catch (\Yiisoft\Payments\Exceptions\InvalidRequestException $e) {
            $this->assertSame('INVALID_SIGNATURE', $e->errorCode);
            $this->assertIsArray($e->details);
            $this->assertSame('Invalid signature.', $e->details['response']['Error'] ?? null);
            throw $e;
        }
    }

    public function testRetrievePaymentIntentUsesOpStateExt(): void
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

        $this->httpClient->queueRawResponse($xml, 200, ['Content-Type' => ['text/xml']]);

        $result = $this->gateway->retrievePaymentIntent('123');

        $this->assertSame('123', $result->id);
        $this->assertSame('SUCCEEDED', $result->status);
        $this->assertSame('OP-123', $result->metadata['robokassa_op_key']);

        $lastRequest = $this->httpClient->lastRequest;
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('Service.asmx/OpStateExt', $lastRequest['uri']);
        $this->assertStringContainsString('MerchantLogin=demo', $lastRequest['body']);
        $this->assertStringContainsString('InvoiceID=123', $lastRequest['body']);
        $this->assertStringContainsString('Signature=', $lastRequest['body']);
    }

    public function testCreateRefundUsesRefundApiV2(): void
    {
        $this->httpClient->queueJsonResponse([
            'success' => true,
            'requestId' => 'REQ-123',
            'message' => null,
        ]);

        $result = $this->gateway->createRefund(
            paymentIntentId: '123',
            amount: 1000
        );

        $this->assertTrue($result['success']);
        $this->assertSame('REQ-123', $result['requestId']);

        $lastRequest = $this->httpClient->lastRequest;
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertStringContainsString('RefundService/Refund/Create', $lastRequest['uri']);
        $this->assertCount(3, explode('.', $lastRequest['body']));
    }
}
