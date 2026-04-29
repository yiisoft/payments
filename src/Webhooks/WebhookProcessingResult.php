<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Normalized result of provider webhook event recognition.
 */
final readonly class WebhookProcessingResult
{
    public function __construct(
        public WebhookProcessingStatus $status,
        public ?WebhookEventType $eventType = null,
        public ?WebhookReason $reason = null,
    ) {
    }

    /**
     * Creates a result for a valid provider webhook event type that is not present in the provider mapping.
     */
    public static function unknownEvent(): self
    {
        return new self(
            status: WebhookProcessingStatus::UnknownEvent,
            reason: new WebhookReason(
                code: new WebhookReasonCode('unknown_event_type'),
                message: 'Provider event type is not recognized by the webhook event mapping.',
            ),
        );
    }
}
