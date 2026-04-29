<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Immutable normalized webhook context returned by webhook processing.
 */
final readonly class WebhookContext
{
    public function __construct(
        public ?string $providerId = null,
        public ?WebhookEventType $eventType = null,
        public ?WebhookProcessingStatus $status = null,
        public ?WebhookReason $validationFailureReason = null,
        public ?WebhookReason $unsupportedEventReason = null,
        public ?WebhookReason $unknownEventReason = null,
        public ?WebhookInput $rawInput = null,
        public ?WebhookRawData $rawData = null,
    ) {
    }
}
