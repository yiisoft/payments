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
use Yiisoft\Payments\Webhooks\WebhookStripeEventRecognizer;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentWebhookMapper;

final class WebhookStripeRefundBoundaryTest extends TestCase
{
    public function testRecognizesChargeRefundedAsRefundLikeEventOutsideR1PaymentNormalization(): void
    {
        $recognizer = new WebhookStripeEventRecognizer();
        $input = new WebhookInput(rawBody: '{"id":"evt_refunded","type":"charge.refunded"}');

        $providerEventType = $recognizer->recognizeProviderEventType($input);

        $this->assertSame('charge.refunded', $providerEventType);
        $this->assertSame(WebhookEventType::PaymentRefunded, $recognizer->recognizeEventType($providerEventType));
        $this->assertFalse(WebhookPaymentOutcomeRules::shouldProcess(WebhookEventType::PaymentRefunded));
        $this->assertTrue(WebhookPaymentOutcomeRules::shouldRejectAsUnsupported(WebhookEventType::PaymentRefunded));
    }

    public function testKeepsChargeRefundedUnsupportedInR1StripeMapper(): void
    {
        $mapper = new WebhookStripePaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '{"type":"charge.refunded","data":{"object":{"id":"ch_123","status":"succeeded"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: [
                'type' => 'charge.refunded',
                'data' => ['object' => ['id' => 'ch_123', 'status' => 'succeeded']],
            ],
            providerEventType: 'charge.refunded',
        );
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: 'charge.refunded',
            data: [
                'type' => 'charge.refunded',
                'data' => ['object' => ['id' => 'ch_123', 'status' => 'succeeded']],
            ],
            paymentStatus: 'succeeded',
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame('charge.refunded', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
        $this->assertNull($result->paymentStatus);
    }
}
