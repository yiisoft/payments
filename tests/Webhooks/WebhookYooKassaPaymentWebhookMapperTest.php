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

    public function testReturnsUnknownEventForPayloadWithoutNormalizedEventType(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: null,
            providerEventType: 'payment.future_event',
            data: ['event' => 'payment.future_event'],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('payment.future_event', $result->reason->providerEventType);
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
}
