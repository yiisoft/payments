<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific recognizer of payment-related webhook event types.
 */
interface WebhookEventRecognizerInterface
{
    /**
     * Returns the raw provider event type as it appears in the incoming webhook request.
     *
     * The returned value must be the provider-defined event name/code without mapping it
     * to a library-level {@see WebhookEventType}. Return null when the request does not
     * contain an event type that can be recognized by this provider.
     */
    public function recognizeProviderEventType(WebhookInput $input): ?string;

    /**
     * Maps a raw provider event type to a normalized library-level webhook event type.
     *
     * Return a {@see WebhookEventType} only when the provider event is recognized as a
     * supported payment-related event. Return null when the provider event is unknown
     * or does not have a normalized R1 payment webhook equivalent.
     */
    public function recognizeEventType(string $providerEventType): ?WebhookEventType;
}
