<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookPayPalPaymentWebhookMapperTest extends TestCase
{
    public function testImplementsPaymentWebhookMapperInterface(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();

        $this->assertInstanceOf(PaymentWebhookMapperInterface::class, $mapper);
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

    public function testReturnsUnknownEventForPayloadWithoutNormalizedEventType(): void
    {
        $mapper = new WebhookPayPalPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: null,
            providerEventType: 'PAYMENT.CAPTURE.FUTURE_EVENT',
            data: ['event_type' => 'PAYMENT.CAPTURE.FUTURE_EVENT'],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('PAYMENT.CAPTURE.FUTURE_EVENT', $result->reason->providerEventType);
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
}
