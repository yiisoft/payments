<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Normalized provider webhook processing outcome.
 *
 * Represents both event recognition results and pre-processing failures such as
 * validation failure or missing provider processor.
 */
final readonly class WebhookProcessingResult
{
    public function __construct(
        public WebhookProcessingStatus $status,
        public ?WebhookEventType $eventType = null,
        public ?WebhookReason $reason = null,
        public ?WebhookRawData $rawData = null,
    ) {
    }

    /**
     * Creates a result for a webhook payload that was mapped successfully.
     */
    public static function processed(WebhookEventType $eventType, ?WebhookRawData $rawData = null): self
    {
        return new self(
            status: WebhookProcessingStatus::Processed,
            eventType: $eventType,
            rawData: $rawData,
        );
    }

    /**
     * Creates a result for a webhook request that failed provider-specific validation.
     */
    public static function validationFailed(?WebhookRawData $rawData = null, ?WebhookReason $reason = null): self
    {
        return new self(
            status: WebhookProcessingStatus::ValidationFailed,
            reason: $reason ?? new WebhookReason(
                code: new WebhookReasonCode('validation_failed'),
                message: 'Webhook request failed provider-specific validation.',
                providerEventType: $rawData?->providerEventType,
            ),
            rawData: $rawData,
        );
    }

    /**
     * Creates a result for a webhook request whose provider processor is not registered.
     */
    public static function missingProviderProcessor(string $providerId, ?WebhookRawData $rawData = null): self
    {
        return new self(
            status: WebhookProcessingStatus::ValidationFailed,
            reason: new WebhookReason(
                code: new WebhookReasonCode('missing_provider_processor'),
                message: sprintf(
                    'Webhook provider processor is not registered for provider "%s".',
                    $providerId,
                ),
                providerEventType: $rawData?->providerEventType,
            ),
            rawData: $rawData,
        );
    }

    /**
     * Creates a result for a valid provider webhook event type that is not present in the provider mapping.
     */
    public static function unknownEvent(string $providerEventType): self
    {
        return new self(
            status: WebhookProcessingStatus::UnknownEvent,
            reason: new WebhookReason(
                code: new WebhookReasonCode('unknown_event_type'),
                message: 'Provider event type is not recognized by the webhook event mapping.',
                providerEventType: $providerEventType,
            ),
        );
    }

    /**
     * Creates a result for a known webhook event type that is recognized but not supported by the current contract.
     */
    public static function unsupportedEvent(
        WebhookEventType $eventType,
        ?string $providerEventType = null,
        ?WebhookRawData $rawData = null,
    ): self
    {
        return new self(
            status: WebhookProcessingStatus::UnsupportedEvent,
            eventType: $eventType,
            reason: new WebhookReason(
                code: new WebhookReasonCode('unsupported_event_type'),
                message: 'Webhook event type is recognized but is not supported by the current webhook contract.',
                providerEventType: $providerEventType,
            ),
            rawData: $rawData,
        );
    }
}
