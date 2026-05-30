<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayloadParserInterface;
use Yiisoft\Payments\Webhooks\WebhookPayPalPayloadParser;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookPayPalPayloadParserTest extends TestCase
{
    public function testImplementsPayloadParserInterface(): void
    {
        $this->assertInstanceOf(WebhookPayloadParserInterface::class, new WebhookPayPalPayloadParser());
    }

    public function testParsesPayPalJsonPayload(): void
    {
        $parser = new WebhookPayPalPayloadParser();
        $input = new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAPTURE-123","status":"COMPLETED"}}',
            headers: ['PayPal-Transmission-Id' => 'transmission-123'],
            providerId: 'paypal',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            'PAYMENT.CAPTURE.COMPLETED',
        );

        $this->assertSame('paypal', $payload->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $payload->eventType);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $payload->providerEventType);
        $this->assertSame('WH-123', $payload->data['id']);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $payload->data['event_type']);
        $this->assertSame('COMPLETED', $payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame($input->rawBody, $payload->rawData->rawBody);
        $this->assertSame($input->headers, $payload->rawData->headers);
        $this->assertSame($payload->data, $payload->rawData->payload);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $payload->rawData->providerEventType);
    }

    public function testPreservesInputDataInRawData(): void
    {
        $parser = new WebhookPayPalPayloadParser();
        $input = new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.PENDING","resource":{"status":"PENDING"}}',
            headers: ['Content-Type' => 'application/json'],
            queryParams: ['debug' => '1'],
            bodyParams: ['ignored' => 'form-value'],
            providerId: 'paypal',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentProcessing,
            'PAYMENT.CAPTURE.PENDING',
        );

        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame(['Content-Type' => 'application/json'], $payload->rawData->headers);
        $this->assertSame(['debug' => '1'], $payload->rawData->queryParams);
        $this->assertSame(['ignored' => 'form-value'], $payload->rawData->bodyParams);
    }

    public function testMalformedPayPalJsonPayloadReturnsEmptyDataAndPreservesRawData(): void
    {
        $parser = new WebhookPayPalPayloadParser();
        $input = new WebhookInput(
            rawBody: '{malformed-json',
            headers: ['Content-Type' => 'application/json'],
            providerId: 'paypal',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            'PAYMENT.CAPTURE.COMPLETED',
        );

        $this->assertSame([], $payload->data);
        $this->assertNull($payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame('{malformed-json', $payload->rawData->rawBody);
        $this->assertSame([], $payload->rawData->payload);
    }
}
