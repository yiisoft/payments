<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;

final class WebhookProcessingResultTest extends TestCase
{
    public function testUnknownProviderEventTypeHasUnknownEventResult(): void
    {
        $result = WebhookProcessingResult::unknownEvent('payment_intent.partially_refunded');

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame(
            'Provider event type is not recognized by the webhook event mapping.',
            $result->reason->message,
        );
        $this->assertSame('payment_intent.partially_refunded', $result->reason->providerEventType);
    }

    public function testValidUnknownProviderEventTypeDoesNotThrowException(): void
    {
        $result = WebhookProcessingResult::unknownEvent('provider.event.not_in_mapping');

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('provider.event.not_in_mapping', $result->reason->providerEventType);
    }

    public function testUnknownProviderEventTypeKeepsRawProviderValueUnchanged(): void
    {
        $result = WebhookProcessingResult::unknownEvent('PAYMENT.CAPTURE.PENDING');

        $this->assertNotNull($result->reason);
        $this->assertSame('PAYMENT.CAPTURE.PENDING', $result->reason->providerEventType);
    }

    public function testUnknownProviderEventTypeDoesNotCreateNormalizedEventType(): void
    {
        $result = WebhookProcessingResult::unknownEvent('invoice.payment_succeeded.extra');

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
    }

    public function testUnknownProviderEventTypeResultCanBeCreatedForDifferentProviderFormats(): void
    {
        $providerEventTypes = [
            'payment_intent.requires_action.unmapped',
            'PAYMENT.CAPTURE.REVERSED.UNMAPPED',
            'payment.waiting_for_capture.unmapped',
            'robokassa.operation.unmapped',
        ];

        foreach ($providerEventTypes as $providerEventType) {
            $result = WebhookProcessingResult::unknownEvent($providerEventType);

            $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
            $this->assertNull($result->eventType);
            $this->assertNotNull($result->reason);
            $this->assertSame($providerEventType, $result->reason->providerEventType);
        }
    }
}
