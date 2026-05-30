<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Intermediate provider-processing representation of a parsed webhook payload.
 */
final readonly class WebhookPayload
{
    /**
     * @param WebhookEventType|null $eventType Normalized library-level webhook event type recognized from the provider event.
     * @param array<string, mixed> $data Parsed provider payload data preserved for provider-specific mapping.
     */
    public function __construct(
        public ?string $providerId = null,
        public ?WebhookEventType $eventType = null,
        public ?string $providerEventType = null,
        public array $data = [],
        public ?string $paymentStatus = null,
        public ?WebhookRawData $rawData = null,
    ) {
    }
}
