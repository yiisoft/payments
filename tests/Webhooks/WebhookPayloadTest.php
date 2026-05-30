<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use Error;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookPayloadTest extends TestCase
{
    public function testPayloadKeepsProviderProcessingData(): void
    {
        $data = [
            'id' => 'evt_123',
            'object' => [
                'id' => 'pi_123',
                'status' => 'succeeded',
            ],
        ];
        $rawData = new WebhookRawData(
            rawBody: '{"id":"evt_123"}',
            headers: ['Stripe-Signature' => 't=123,v1=abc'],
            payload: $data,
            providerEventType: 'payment_intent.succeeded',
        );

        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            data: $data,
            paymentStatus: 'succeeded',
            rawData: $rawData,
        );

        $this->assertSame('stripe', $payload->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $payload->eventType);
        $this->assertSame('payment_intent.succeeded', $payload->providerEventType);
        $this->assertSame($data, $payload->data);
        $this->assertSame('succeeded', $payload->paymentStatus);
        $this->assertSame($rawData, $payload->rawData);
    }

    public function testPayloadCanBeCreatedWithoutRecognizedProviderDataYet(): void
    {
        $payload = new WebhookPayload();

        $this->assertNull($payload->providerId);
        $this->assertNull($payload->eventType);
        $this->assertNull($payload->providerEventType);
        $this->assertSame([], $payload->data);
        $this->assertNull($payload->paymentStatus);
        $this->assertNull($payload->rawData);
    }

    public function testPayloadDefinesExpectedFields(): void
    {
        $reflection = new ReflectionClass(WebhookPayload::class);

        $this->assertSame(
            [
                'providerId',
                'eventType',
                'providerEventType',
                'data',
                'paymentStatus',
                'rawData',
            ],
            array_map(
                static fn ($property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );
    }

    public function testPayloadIsImmutableValueObject(): void
    {
        $reflection = new ReflectionClass(WebhookPayload::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->getProperty('providerId')->isReadOnly());
        $this->assertTrue($reflection->getProperty('eventType')->isReadOnly());
        $this->assertTrue($reflection->getProperty('providerEventType')->isReadOnly());
        $this->assertTrue($reflection->getProperty('data')->isReadOnly());
        $this->assertTrue($reflection->getProperty('paymentStatus')->isReadOnly());
        $this->assertTrue($reflection->getProperty('rawData')->isReadOnly());
    }

    public function testPayloadRejectsPropertyReassignment(): void
    {
        $payload = new WebhookPayload(providerId: 'stripe');

        $this->expectException(Error::class);

        $payload->providerId = 'paypal';
    }
}
