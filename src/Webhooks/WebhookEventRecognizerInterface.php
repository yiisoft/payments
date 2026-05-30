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

    public function recognizeEventType(string $providerEventType): ?WebhookEventType;
}
