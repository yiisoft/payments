<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookPaymentOutcomeRules;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookYooKassaEventRecognizer;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentWebhookMapper;

final class WebhookYooKassaRefundBoundaryTest extends TestCase
{
    public function testRecognizesRefundSucceededAsRefundLikeEventOutsideR1PaymentNormalization(): void
    {
        $recognizer = new WebhookYooKassaEventRecognizer();
        $input = new WebhookInput(
            rawBody: '{"type":"notification","event":"refund.succeeded","object":{"status":"succeeded"}}',
        );

        $providerEventType = $recognizer->recognizeProviderEventType($input);

        $this->assertSame('refund.succeeded', $providerEventType);
        $this->assertSame(WebhookEventType::PaymentRefunded, $recognizer->recognizeEventType($providerEventType));
        $this->assertFalse(WebhookPaymentOutcomeRules::shouldProcess(WebhookEventType::PaymentRefunded));
        $this->assertTrue(WebhookPaymentOutcomeRules::shouldRejectAsUnsupported(WebhookEventType::PaymentRefunded));
    }

    public function testKeepsRefundSucceededUnsupportedInR1YooKassaMapper(): void
    {
        $mapper = new WebhookYooKassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '{"type":"notification","event":"refund.succeeded","object":{"status":"succeeded"}}',
            headers: ['Content-Type' => 'application/json'],
            payload: [
                'type' => 'notification',
                'event' => 'refund.succeeded',
                'object' => ['status' => 'succeeded'],
            ],
            providerEventType: 'refund.succeeded',
        );
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: 'refund.succeeded',
            data: [
                'type' => 'notification',
                'event' => 'refund.succeeded',
                'object' => ['status' => 'succeeded'],
            ],
            paymentStatus: 'succeeded',
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame('refund.succeeded', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
        $this->assertNull($result->paymentStatus);
    }
}
