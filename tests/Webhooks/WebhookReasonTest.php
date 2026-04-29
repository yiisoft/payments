<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;

final class WebhookReasonTest extends TestCase
{
    public function testReasonStoresOptionalProviderEventType(): void
    {
        $code = new WebhookReasonCode('unknown_event_type');

        $reason = new WebhookReason(
            code: $code,
            message: 'Provider event type is not recognized.',
            providerEventType: 'payment_intent.unknown',
        );

        $this->assertSame($code, $reason->code);
        $this->assertSame('Provider event type is not recognized.', $reason->message);
        $this->assertSame('payment_intent.unknown', $reason->providerEventType);
    }

    public function testProviderEventTypeIsOptional(): void
    {
        $reason = new WebhookReason(
            code: new WebhookReasonCode('unknown_event_type'),
            message: 'Provider event type is not recognized.',
        );

        $this->assertNull($reason->providerEventType);
    }

    public function testProviderEventTypeMustBeNonEmptyWhenProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook provider event type must be null or a non-empty string.');

        new WebhookReason(
            code: new WebhookReasonCode('unknown_event_type'),
            message: 'Provider event type is not recognized.',
            providerEventType: '   ',
        );
    }
}
