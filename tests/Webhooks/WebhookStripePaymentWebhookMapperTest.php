<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentWebhookMapper;

final class WebhookStripePaymentWebhookMapperTest extends TestCase
{
    public function testImplementsPaymentWebhookMapperInterface(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();

        $this->assertInstanceOf(PaymentWebhookMapperInterface::class, $mapper);
    }

    public function testMapsSuccessfulStripePaymentPayload(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '{"type":"payment_intent.succeeded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: ['type' => 'payment_intent.succeeded'],
            providerEventType: 'payment_intent.succeeded',
        );
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            data: ['type' => 'payment_intent.succeeded'],
            paymentStatus: 'succeeded',
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertSame($rawData, $result->rawData);
    }

    #[DataProvider('supportedStripePaymentOutcomeProvider')]
    public function testMapsSupportedStripePaymentOutcomePayloads(
        WebhookEventType $eventType,
        string $providerEventType,
        string $paymentStatus,
    ): void {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: sprintf('{"type":"%s"}', $providerEventType),
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: ['type' => $providerEventType],
            providerEventType: $providerEventType,
        );
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: $eventType,
            providerEventType: $providerEventType,
            data: ['data' => ['object' => ['status' => $paymentStatus]]],
            paymentStatus: $paymentStatus,
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame($eventType, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertSame($rawData, $result->rawData);
        $this->assertSame($paymentStatus, $result->paymentStatus);
    }

    public function testKeepsUnsupportedResultForRecognizedStripeRefundLikePayload(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '{"type":"charge.refunded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: ['type' => 'charge.refunded'],
            providerEventType: 'charge.refunded',
        );
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: 'charge.refunded',
            data: ['type' => 'charge.refunded'],
            paymentStatus: 'succeeded',
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame(
            'Webhook event type is recognized but is not supported by the current webhook contract.',
            $result->reason->message,
        );
        $this->assertSame('charge.refunded', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testReturnsUnknownEventForPayloadWithoutNormalizedEventType(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '{"type":"payment_intent.future_event"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: ['type' => 'payment_intent.future_event'],
            providerEventType: 'payment_intent.future_event',
        );
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: null,
            providerEventType: 'payment_intent.future_event',
            data: ['type' => 'payment_intent.future_event'],
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('payment_intent.future_event', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testExtractsStripePaymentStatusFromPayload(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            data: ['data' => ['object' => ['status' => 'succeeded']]],
            paymentStatus: 'succeeded',
        );

        $this->assertSame('succeeded', $mapper->extractPaymentStatus($payload));
    }

    public function testExtractsStripePaymentStatusFromProviderObjectWhenPayloadStatusIsMissing(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentProcessing,
            providerEventType: 'payment_intent.processing',
            data: ['data' => ['object' => ['status' => 'processing']]],
        );

        $this->assertSame('processing', $mapper->extractPaymentStatus($payload));
    }

    public function testKeepsExplicitStripePaymentStatusWhenProviderObjectContainsDifferentStatus(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            data: ['data' => ['object' => ['status' => 'processing']]],
            paymentStatus: 'succeeded',
        );

        $this->assertSame('succeeded', $mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullWhenStripeProviderObjectStatusIsNotString(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            data: ['data' => ['object' => ['status' => ['succeeded']]]],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullWhenStripePaymentStatusIsMissing(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            data: ['type' => 'payment_intent.succeeded'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testMapsSuccessfulStripePaymentPayloadWithoutRawData(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            data: ['type' => 'payment_intent.succeeded'],
            paymentStatus: 'succeeded',
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNull($result->rawData);
    }

    public function testReturnsUnknownEventForPayloadWithoutProviderEventType(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: null,
            providerEventType: null,
            data: ['type' => 'payment_intent.future_event'],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public static function supportedStripePaymentOutcomeProvider(): iterable
    {
        yield 'failed payment intent' => [
            WebhookEventType::PaymentFailed,
            'payment_intent.payment_failed',
            'requires_payment_method',
        ];
        yield 'canceled payment intent' => [
            WebhookEventType::PaymentCanceled,
            'payment_intent.canceled',
            'canceled',
        ];
        yield 'processing payment intent' => [
            WebhookEventType::PaymentProcessing,
            'payment_intent.processing',
            'processing',
        ];
        yield 'requires capture payment intent' => [
            WebhookEventType::PaymentRequiresCapture,
            'payment_intent.amount_capturable_updated',
            'requires_capture',
        ];
    }
}
