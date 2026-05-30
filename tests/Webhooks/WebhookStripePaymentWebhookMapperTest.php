<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

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

    /**
     * @dataProvider unsupportedStripePaymentPayloadProvider
     */
    public function testKeepsUnsupportedResultForOtherRecognizedStripePaymentPayloads(
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
            data: ['type' => $providerEventType],
            paymentStatus: $paymentStatus,
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame($eventType, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame(
            'Webhook event type is recognized but is not supported by the current webhook contract.',
            $result->reason->message,
        );
        $this->assertSame($providerEventType, $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public static function unsupportedStripePaymentPayloadProvider(): array
    {
        return [
            'processing payment intent' => [
                WebhookEventType::PaymentProcessing,
                'payment_intent.processing',
                'processing',
            ],
            'payment intent requires action' => [
                WebhookEventType::PaymentRequiresAction,
                'payment_intent.requires_action',
                'requires_action',
            ],
            'payment intent requires capture' => [
                WebhookEventType::PaymentRequiresCapture,
                'payment_intent.amount_capturable_updated',
                'requires_capture',
            ],
            'failed payment intent' => [
                WebhookEventType::PaymentFailed,
                'payment_intent.payment_failed',
                'requires_payment_method',
            ],
            'canceled payment intent' => [
                WebhookEventType::PaymentCanceled,
                'payment_intent.canceled',
                'canceled',
            ],
            'partially supported refunded charge' => [
                WebhookEventType::PaymentRefunded,
                'charge.refunded',
                'succeeded',
            ],
        ];
    }

    public function testReturnsUnknownEventForPayloadWithoutNormalizedEventType(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: null,
            providerEventType: 'payment_intent.future_event',
            data: ['type' => 'payment_intent.future_event'],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('payment_intent.future_event', $result->reason->providerEventType);
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
}
