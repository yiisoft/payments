<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayloadParserInterface;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookStripePayloadParser;

final class WebhookStripePayloadParserTest extends TestCase
{
    public function testImplementsPayloadParserInterface(): void
    {
        $this->assertInstanceOf(WebhookPayloadParserInterface::class, new WebhookStripePayloadParser());
    }

    public function testParsesStripeJsonPayload(): void
    {
        $parser = new WebhookStripePayloadParser();
        $input = new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded","data":{"object":{"id":"pi_123","status":"succeeded"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=abc'],
            providerId: 'stripe',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            'payment_intent.succeeded',
        );

        $this->assertSame('stripe', $payload->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $payload->eventType);
        $this->assertSame('payment_intent.succeeded', $payload->providerEventType);
        $this->assertSame('evt_123', $payload->data['id']);
        $this->assertSame('payment_intent.succeeded', $payload->data['type']);
        $this->assertSame('succeeded', $payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame($input->rawBody, $payload->rawData->rawBody);
        $this->assertSame($input->headers, $payload->rawData->headers);
        $this->assertSame($payload->data, $payload->rawData->payload);
        $this->assertSame('payment_intent.succeeded', $payload->rawData->providerEventType);
    }

    public function testPreservesInputDataInRawData(): void
    {
        $parser = new WebhookStripePayloadParser();
        $input = new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.processing","data":{"object":{"status":"processing"}}}',
            headers: ['Content-Type' => 'application/json'],
            queryParams: ['debug' => '1'],
            bodyParams: ['ignored' => 'form-value'],
            providerId: 'stripe',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentProcessing,
            'payment_intent.processing',
        );

        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame(['Content-Type' => 'application/json'], $payload->rawData->headers);
        $this->assertSame(['debug' => '1'], $payload->rawData->queryParams);
        $this->assertSame(['ignored' => 'form-value'], $payload->rawData->bodyParams);
    }

    public function testMalformedStripeJsonPayloadReturnsEmptyDataAndPreservesRawData(): void
    {
        $parser = new WebhookStripePayloadParser();
        $input = new WebhookInput(
            rawBody: '{malformed-json',
            headers: ['Content-Type' => 'application/json'],
            providerId: 'stripe',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            'payment_intent.succeeded',
        );

        $this->assertSame([], $payload->data);
        $this->assertNull($payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame('{malformed-json', $payload->rawData->rawBody);
        $this->assertSame([], $payload->rawData->payload);
    }
}
