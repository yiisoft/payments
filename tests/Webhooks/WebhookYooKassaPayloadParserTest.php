<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayloadParserInterface;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPayloadParser;

final class WebhookYooKassaPayloadParserTest extends TestCase
{
    public function testImplementsPayloadParserInterface(): void
    {
        $this->assertInstanceOf(WebhookPayloadParserInterface::class, new WebhookYooKassaPayloadParser());
    }

    public function testParsesYooKassaJsonPayload(): void
    {
        $parser = new WebhookYooKassaPayloadParser();
        $input = new WebhookInput(
            rawBody: '{"type":"notification","event":"payment.succeeded","object":{"id":"payment-123","status":"succeeded"}}',
            headers: ['Content-Type' => 'application/json'],
            providerId: 'yookassa',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            'payment.succeeded',
        );

        $this->assertSame('yookassa', $payload->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $payload->eventType);
        $this->assertSame('payment.succeeded', $payload->providerEventType);
        $this->assertSame('notification', $payload->data['type']);
        $this->assertSame('payment.succeeded', $payload->data['event']);
        $this->assertSame('succeeded', $payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame($input->rawBody, $payload->rawData->rawBody);
        $this->assertSame($input->headers, $payload->rawData->headers);
        $this->assertSame($payload->data, $payload->rawData->payload);
        $this->assertSame('payment.succeeded', $payload->rawData->providerEventType);
    }

    public function testPreservesInputDataInRawData(): void
    {
        $parser = new WebhookYooKassaPayloadParser();
        $input = new WebhookInput(
            rawBody: '{"type":"notification","event":"payment.waiting_for_capture","object":{"status":"waiting_for_capture"}}',
            headers: ['Content-Type' => 'application/json'],
            queryParams: ['debug' => '1'],
            bodyParams: ['ignored' => 'form-value'],
            providerId: 'yookassa',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentRequiresCapture,
            'payment.waiting_for_capture',
        );

        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame(['Content-Type' => 'application/json'], $payload->rawData->headers);
        $this->assertSame(['debug' => '1'], $payload->rawData->queryParams);
        $this->assertSame(['ignored' => 'form-value'], $payload->rawData->bodyParams);
    }

    public function testMalformedYooKassaJsonPayloadReturnsEmptyDataAndPreservesRawData(): void
    {
        $parser = new WebhookYooKassaPayloadParser();
        $input = new WebhookInput(
            rawBody: '{malformed-json',
            headers: ['Content-Type' => 'application/json'],
            providerId: 'yookassa',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            'payment.succeeded',
        );

        $this->assertSame([], $payload->data);
        $this->assertNull($payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame('{malformed-json', $payload->rawData->rawBody);
        $this->assertSame([], $payload->rawData->payload);
    }
}
