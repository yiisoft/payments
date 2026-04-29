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
        $result = WebhookProcessingResult::unknownEvent();

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame(
            'Provider event type is not recognized by the webhook event mapping.',
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }
}
