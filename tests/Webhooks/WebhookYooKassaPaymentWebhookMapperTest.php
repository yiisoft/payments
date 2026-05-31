<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentWebhookMapper;

final class WebhookYooKassaPaymentWebhookMapperTest extends TestCase
{
    public function testImplementsPaymentWebhookMapperInterface(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();

        $this->assertInstanceOf(PaymentWebhookMapperInterface::class, $mapper);
    }

    public function testMapsSuccessfulYooKassaPaymentPayload(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '{"event":"payment.succeeded"}',
            headers: ['Content-Type' => 'application/json'],
            payload: ['event' => 'payment.succeeded'],
            providerEventType: 'payment.succeeded',
        );
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment.succeeded',
            data: ['event' => 'payment.succeeded'],
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
     * @dataProvider processedYooKassaNonSuccessPaymentOutcomeProvider
     */
    public function testProcessesYooKassaNonSuccessPaymentOutcomes(
        WebhookEventType $eventType,
        string $providerEventType,
        string $paymentStatus,
    ): void {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: sprintf(
                '{"type":"notification","event":"%s","object":{"status":"%s"}}',
                $providerEventType,
                $paymentStatus,
            ),
            headers: ['Content-Type' => 'application/json'],
            payload: [
                'type' => 'notification',
                'event' => $providerEventType,
                'object' => ['status' => $paymentStatus],
            ],
            providerEventType: $providerEventType,
        );
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: $eventType,
            providerEventType: $providerEventType,
            data: [
                'type' => 'notification',
                'event' => $providerEventType,
                'object' => ['status' => $paymentStatus],
            ],
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

    public static function processedYooKassaNonSuccessPaymentOutcomeProvider(): array
    {
        return [
            'canceled payment' => [
                WebhookEventType::PaymentCanceled,
                'payment.canceled',
                'canceled',
            ],
            'waiting for capture payment' => [
                WebhookEventType::PaymentRequiresCapture,
                'payment.waiting_for_capture',
                'waiting_for_capture',
            ],
        ];
    }

    /**
     * @dataProvider unsupportedYooKassaRefundLikePayloadProvider
     */
    public function testKeepsUnsupportedResultForRecognizedYooKassaRefundLikePayloads(
        WebhookEventType $eventType,
        string $providerEventType,
        string $paymentStatus,
    ): void {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: sprintf('{"event":"%s"}', $providerEventType),
            headers: ['Content-Type' => 'application/json'],
            payload: ['event' => $providerEventType],
            providerEventType: $providerEventType,
        );
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: $eventType,
            providerEventType: $providerEventType,
            data: ['event' => $providerEventType],
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

    public static function unsupportedYooKassaRefundLikePayloadProvider(): array
    {
        return [
            'partially supported refund succeeded' => [
                WebhookEventType::PaymentRefunded,
                'refund.succeeded',
                'succeeded',
            ],
        ];
    }

    public function testReturnsUnknownEventForPayloadWithoutNormalizedEventType(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '{"event":"payment.future_event"}',
            headers: ['Content-Type' => 'application/json'],
            payload: ['event' => 'payment.future_event'],
            providerEventType: 'payment.future_event',
        );
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: null,
            providerEventType: 'payment.future_event',
            data: ['event' => 'payment.future_event'],
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('payment.future_event', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testExtractsYooKassaPaymentStatusFromPayload(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment.succeeded',
            data: ['object' => ['status' => 'succeeded']],
            paymentStatus: 'succeeded',
        );

        $this->assertSame('succeeded', $mapper->extractPaymentStatus($payload));
    }

    public function testExtractsYooKassaPaymentStatusFromProviderObject(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: WebhookEventType::PaymentRequiresCapture,
            providerEventType: 'payment.waiting_for_capture',
            data: ['object' => ['status' => 'waiting_for_capture']],
        );

        $this->assertSame('waiting_for_capture', $mapper->extractPaymentStatus($payload));
    }

    public function testPrefersExplicitYooKassaPaymentStatusFromPayload(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment.succeeded',
            data: ['object' => ['status' => 'pending']],
            paymentStatus: 'succeeded',
        );

        $this->assertSame('succeeded', $mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullWhenYooKassaProviderObjectStatusIsNotString(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment.succeeded',
            data: ['object' => ['status' => 1]],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullWhenYooKassaPaymentStatusIsMissing(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment.succeeded',
            data: ['event' => 'payment.succeeded'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testMapsSuccessfulYooKassaPaymentPayloadWithoutRawData(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment.succeeded',
            data: ['event' => 'payment.succeeded'],
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
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: null,
            providerEventType: null,
            data: ['event' => 'payment.future_event'],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }
}
