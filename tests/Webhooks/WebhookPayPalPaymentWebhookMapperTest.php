<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookPayPalPaymentWebhookMapperTest extends TestCase
{
    public function testImplementsWebhookPaymentMapperInterface(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();

        $this->assertInstanceOf(WebhookPaymentMapperInterface::class, $mapper);
    }

    public function testMapsSuccessfulPayPalPaymentPayload(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            payload: ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'],
            providerEventType: 'PAYMENT.CAPTURE.COMPLETED',
        );
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'PAYMENT.CAPTURE.COMPLETED',
            data: ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'],
            paymentStatus: 'COMPLETED',
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertSame($rawData, $result->rawData);
    }


    /**
     * @dataProvider processedPayPalNonSuccessPaymentOutcomeProvider
     */
    public function testProcessesPayPalNonSuccessPaymentOutcomes(
        WebhookEventType $eventType,
        string $providerEventType,
        string $paymentStatus,
    ): void {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: sprintf('{"event_type":"%s","resource":{"status":"%s"}}', $providerEventType, $paymentStatus),
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            payload: ['event_type' => $providerEventType, 'resource' => ['status' => $paymentStatus]],
            providerEventType: $providerEventType,
        );
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: $eventType,
            providerEventType: $providerEventType,
            data: ['event_type' => $providerEventType, 'resource' => ['status' => $paymentStatus]],
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

    public static function processedPayPalNonSuccessPaymentOutcomeProvider(): array
    {
        return [
            'failed denied capture' => [
                WebhookEventType::PaymentFailed,
                'PAYMENT.CAPTURE.DENIED',
                'DENIED',
            ],
            'failed declined capture' => [
                WebhookEventType::PaymentFailed,
                'PAYMENT.CAPTURE.DECLINED',
                'DECLINED',
            ],
            'canceled reversed approval' => [
                WebhookEventType::PaymentCanceled,
                'CHECKOUT.PAYMENT-APPROVAL.REVERSED',
                'REVERSED',
            ],
            'pending capture' => [
                WebhookEventType::PaymentProcessing,
                'PAYMENT.CAPTURE.PENDING',
                'PENDING',
            ],
            'authorization created' => [
                WebhookEventType::PaymentRequiresCapture,
                'PAYMENT.AUTHORIZATION.CREATED',
                'CREATED',
            ],
        ];
    }

    /**
     * @dataProvider unsupportedPayPalRefundLikePayloadProvider
     */
    public function testKeepsUnsupportedResultForPayPalRefundLikePayloads(
        WebhookEventType $eventType,
        string $providerEventType,
        string $paymentStatus,
    ): void {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: sprintf('{"event_type":"%s"}', $providerEventType),
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            payload: ['event_type' => $providerEventType],
            providerEventType: $providerEventType,
        );
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: $eventType,
            providerEventType: $providerEventType,
            data: ['event_type' => $providerEventType],
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

    public static function unsupportedPayPalRefundLikePayloadProvider(): array
    {
        return [
            'partially supported refunded capture' => [
                WebhookEventType::PaymentRefunded,
                'PAYMENT.CAPTURE.REFUNDED',
                'REFUNDED',
            ],
            'partially supported reversed capture' => [
                WebhookEventType::PaymentRefunded,
                'PAYMENT.CAPTURE.REVERSED',
                'REVERSED',
            ],
        ];
    }

    public function testReturnsUnknownEventForPayloadWithoutNormalizedEventType(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.FUTURE_EVENT"}',
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            payload: ['event_type' => 'PAYMENT.CAPTURE.FUTURE_EVENT'],
            providerEventType: 'PAYMENT.CAPTURE.FUTURE_EVENT',
        );
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: null,
            providerEventType: 'PAYMENT.CAPTURE.FUTURE_EVENT',
            data: ['event_type' => 'PAYMENT.CAPTURE.FUTURE_EVENT'],
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('PAYMENT.CAPTURE.FUTURE_EVENT', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testExtractsPayPalPaymentStatusFromPayload(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'PAYMENT.CAPTURE.COMPLETED',
            data: ['resource' => ['status' => 'COMPLETED']],
            paymentStatus: 'COMPLETED',
        );

        $this->assertSame('COMPLETED', $mapper->extractPaymentStatus($payload));
    }

    public function testExtractsPayPalPaymentStatusFromProviderResourceWhenPayloadStatusIsMissing(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentProcessing,
            providerEventType: 'PAYMENT.CAPTURE.PENDING',
            data: ['resource' => ['status' => 'PENDING']],
        );

        $this->assertSame('PENDING', $mapper->extractPaymentStatus($payload));
    }

    public function testKeepsExplicitPayPalPaymentStatusWhenProviderResourceContainsDifferentStatus(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'PAYMENT.CAPTURE.COMPLETED',
            data: ['resource' => ['status' => 'PENDING']],
            paymentStatus: 'COMPLETED',
        );

        $this->assertSame('COMPLETED', $mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullWhenPayPalProviderResourceStatusIsNotString(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'PAYMENT.CAPTURE.COMPLETED',
            data: ['resource' => ['status' => ['COMPLETED']]],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullWhenPayPalPaymentStatusIsMissing(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'PAYMENT.CAPTURE.COMPLETED',
            data: ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testMapsSuccessfulPayPalPaymentPayloadWithoutRawData(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'PAYMENT.CAPTURE.COMPLETED',
            data: ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'],
            paymentStatus: 'COMPLETED',
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNull($result->rawData);
    }

    public function testReturnsUnknownEventForPayloadWithoutProviderEventType(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: null,
            providerEventType: null,
            data: ['event_type' => 'PAYMENT.CAPTURE.FUTURE_EVENT'],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }
}
