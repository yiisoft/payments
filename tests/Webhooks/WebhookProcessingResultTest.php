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
}
