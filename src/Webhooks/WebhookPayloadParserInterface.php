<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific parser of incoming webhook request data into an intermediate payload.
 */
interface WebhookPayloadParserInterface
{
    /**
     * Parses a recognized webhook request into an intermediate provider payload.
     *
     * The input contains the raw request data received from the application, while
     * the event type is the normalized library-level payment webhook event selected
     * before parsing. The returned {@see WebhookPayload} must preserve the data
     * required by provider-specific mapping.
     *
     * Malformed provider payloads must not be converted into application-specific
     * data or hide the original request. When the provider payload cannot be
     * decoded safely, implementations should return a {@see WebhookPayload} with
     * an empty decoded data array and preserved raw data so later processing can
     * produce a predictable failure, diagnostics, or fallback result.
     */
    public function parsePayload(
        WebhookInput $input,
        WebhookEventType $eventType,
        ?string $providerEventType = null,
    ): WebhookPayload;
}
