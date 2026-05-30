<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Intermediate provider-processing representation of a parsed webhook payload.
 */
final readonly class WebhookPayload
{
    /**
     * @param string|null $providerId Provider identifier associated with the incoming webhook request.
     * @param WebhookEventType|null $eventType Normalized library-level webhook event type recognized from the provider event.
     * @param string|null $providerEventType Raw provider-defined event name or code extracted from the request.
     * @param array<string, mixed> $data Decoded provider payload data preserved for provider-specific mapping.
     * @param string|null $paymentStatus Provider payment status extracted from the decoded payload, if available.
     * @param WebhookRawData|null $rawData Raw webhook request data preserved for diagnostics and fallback handling.
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
