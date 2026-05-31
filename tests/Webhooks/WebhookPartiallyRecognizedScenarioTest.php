<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;

final class WebhookPartiallyRecognizedScenarioTest extends TestCase
{
    public function testPartiallyRecognizedProviderEventTypeScenarioReturnsUnsupportedEventResult(): void
    {
        $providerEventType = 'payment_intent.amount_capturable_updated';
        $rawData = new WebhookRawData(
            rawBody: '{"type":"payment_intent.amount_capturable_updated","data":{"object":{"id":"pi_123","status":"requires_capture"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: [
                'type' => 'payment_intent.amount_capturable_updated',
                'data' => ['object' => ['id' => 'pi_123', 'status' => 'requires_capture']],
            ],
            providerEventType: $providerEventType,
        );
        $partiallyRecognizedProviderEvents = ['payment_intent.amount_capturable_updated'];

        $result = $this->recognizePartiallyRecognizedProviderEvent(
            $providerEventType,
            $partiallyRecognizedProviderEvents,
            $rawData,
        );

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame(
            'Provider event type was recognized only partially and cannot be normalized by the current webhook contract.',
            $result->reason->message,
        );
        $this->assertSame($providerEventType, $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
        $this->assertSame(
            '{"type":"payment_intent.amount_capturable_updated","data":{"object":{"id":"pi_123","status":"requires_capture"}}}',
            $result->rawData->rawBody,
        );
        $this->assertSame(['Stripe-Signature' => 't=123,v1=signature'], $result->rawData->headers);
        $this->assertSame(
            [
                'type' => 'payment_intent.amount_capturable_updated',
                'data' => ['object' => ['id' => 'pi_123', 'status' => 'requires_capture']],
            ],
            $result->rawData->payload,
        );
        $this->assertSame($providerEventType, $result->rawData->providerEventType);
    }

    /**
     * @param list<string> $partiallyRecognizedProviderEvents
     */
    private function recognizePartiallyRecognizedProviderEvent(
        string $providerEventType,
        array $partiallyRecognizedProviderEvents,
        WebhookRawData $rawData,
    ): WebhookProcessingResult {
        if (!in_array($providerEventType, $partiallyRecognizedProviderEvents, true)) {
            return WebhookProcessingResult::unknownEvent($providerEventType);
        }

        return new WebhookProcessingResult(
            status: WebhookProcessingStatus::UnsupportedEvent,
            reason: new WebhookReason(
                code: new WebhookReasonCode('unsupported_event_type'),
                message: 'Provider event type was recognized only partially and cannot be normalized by the current webhook contract.',
                providerEventType: $providerEventType,
            ),
            rawData: $rawData,
        );
    }
}
