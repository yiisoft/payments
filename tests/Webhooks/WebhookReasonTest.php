<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;

final class WebhookReasonTest extends TestCase
{
    public function testReasonCodeStoresValue(): void
    {
        $code = new WebhookReasonCode('unknown_event_type');

        $this->assertSame('unknown_event_type', $code->value);
        $this->assertSame('unknown_event_type', (string) $code);
    }

    public function testReasonCodeMustBeNonEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook reason code must be a non-empty string.');

        new WebhookReasonCode('   ');
    }

    public function testReasonStoresCodeAndMessage(): void
    {
        $code = new WebhookReasonCode('unsupported_event_type');

        $reason = new WebhookReason(
            code: $code,
            message: 'Provider event type is not supported by this gateway.',
        );

        $this->assertSame($code, $reason->code);
        $this->assertSame('Provider event type is not supported by this gateway.', $reason->message);
        $this->assertNull($reason->providerEventType);
    }

    public function testReasonMessageMustBeNonEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook reason message must be a non-empty string.');

        new WebhookReason(
            code: new WebhookReasonCode('unknown_event_type'),
            message: '   ',
        );
    }

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
