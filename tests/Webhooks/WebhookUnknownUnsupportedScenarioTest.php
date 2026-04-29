<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookCapabilities;
use Yiisoft\Payments\Webhooks\WebhookCapability;
use Yiisoft\Payments\Webhooks\WebhookEntityKind;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookSupportStatus;

final class WebhookUnknownUnsupportedScenarioTest extends TestCase
{
    public function testUnknownProviderEventTypeScenarioReturnsUnknownEventResult(): void
    {
        $providerEventType = 'payment_intent.partially_refunded';
        $providerMapping = [
            'payment_intent.succeeded' => WebhookEventType::PaymentSucceeded,
            'payment_intent.payment_failed' => WebhookEventType::PaymentFailed,
            'payment_intent.canceled' => WebhookEventType::PaymentCanceled,
        ];

        $result = $this->recognizeProviderEvent($providerEventType, $providerMapping);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame(
            'Provider event type is not recognized by the webhook event mapping.',
            $result->reason->message,
        );
        $this->assertSame($providerEventType, $result->reason->providerEventType);
        $this->assertNull($result->rawData);
    }

    public function testUnsupportedProviderEventTypeScenarioReturnsUnsupportedEventResult(): void
    {
        $providerEventType = 'charge.refunded';
        $rawData = new WebhookRawData(
            rawBody: '{"type":"charge.refunded","data":{"object":{"id":"ch_123"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: ['type' => 'charge.refunded', 'data' => ['object' => ['id' => 'ch_123']]],
            providerEventType: $providerEventType,
        );
        $providerMapping = [
            'payment_intent.succeeded' => WebhookEventType::PaymentSucceeded,
            'payment_intent.payment_failed' => WebhookEventType::PaymentFailed,
            'payment_intent.canceled' => WebhookEventType::PaymentCanceled,
            'charge.refunded' => WebhookEventType::PaymentRefunded,
        ];
        $capabilities = new WebhookCapabilities(
            new WebhookCapability(
                eventType: WebhookEventType::PaymentSucceeded,
                entityKind: WebhookEntityKind::Payment,
                supportStatus: WebhookSupportStatus::Supported,
            ),
            new WebhookCapability(
                eventType: WebhookEventType::PaymentRefunded,
                entityKind: WebhookEntityKind::Payment,
                supportStatus: WebhookSupportStatus::Unsupported,
            ),
        );

        $result = $this->recognizeProviderEvent(
            $providerEventType,
            $providerMapping,
            $capabilities,
            $rawData,
        );

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame(
            'Webhook event type is recognized but is not supported by the current webhook contract.',
            $result->reason->message,
        );
        $this->assertSame($providerEventType, $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
        $this->assertSame('{"type":"charge.refunded","data":{"object":{"id":"ch_123"}}}', $result->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 't=123,v1=signature'], $result->rawData->headers);
        $this->assertSame(['type' => 'charge.refunded', 'data' => ['object' => ['id' => 'ch_123']]], $result->rawData->payload);
        $this->assertSame($providerEventType, $result->rawData->providerEventType);
    }

    /**
     * @param array<string, WebhookEventType> $providerMapping
     */
    private function recognizeProviderEvent(
        string $providerEventType,
        array $providerMapping,
        ?WebhookCapabilities $capabilities = null,
        ?WebhookRawData $rawData = null,
    ): WebhookProcessingResult {
        if (!array_key_exists($providerEventType, $providerMapping)) {
            return WebhookProcessingResult::unknownEvent($providerEventType);
        }

        $eventType = $providerMapping[$providerEventType];
        $unsupportedResult = $capabilities?->unsupportedResultFor(
            $eventType,
            WebhookEntityKind::Payment,
            $providerEventType,
            $rawData,
        );

        if ($unsupportedResult !== null) {
            return $unsupportedResult;
        }

        return new WebhookProcessingResult(
            WebhookProcessingStatus::Processed,
            $eventType,
        );
    }
}
