<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;

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

    /**
     * @param array<string, WebhookEventType> $providerMapping
     */
    private function recognizeProviderEvent(
        string $providerEventType,
        array $providerMapping,
    ): WebhookProcessingResult {
        if (!array_key_exists($providerEventType, $providerMapping)) {
            return WebhookProcessingResult::unknownEvent($providerEventType);
        }

        return new WebhookProcessingResult(
            WebhookProcessingStatus::Processed,
            $providerMapping[$providerEventType],
        );
    }
}
