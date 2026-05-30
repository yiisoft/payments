<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPaymentWebhookMapper;

final class WebhookRobokassaPaymentWebhookMapperTest extends TestCase
{
    public function testImplementsPaymentWebhookMapperInterface(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();

        $this->assertInstanceOf(PaymentWebhookMapperInterface::class, $mapper);
    }

    public function testMapsSupportedRobokassaCallbackToProcessedResult(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            payload: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            bodyParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testMapsSupportedRobokassaCallbackToProcessedResultWithoutRawData(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNull($result->rawData);
    }

    public function testReturnsUnknownEventForPayloadWithoutNormalizedEventType(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: null,
            providerEventType: 'unsupported_callback',
            data: ['OutSum' => '100.00'],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('unsupported_callback', $result->reason->providerEventType);
    }

    public function testDoesNotExtractRobokassaPaymentStatusAtSkeletonStage(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            data: ['OutSum' => '100.00'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }
}
