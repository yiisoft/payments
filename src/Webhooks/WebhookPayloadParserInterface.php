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
     */
    public function parsePayload(
        WebhookInput $input,
        WebhookEventType $eventType,
        ?string $providerEventType = null,
    ): WebhookPayload;
}
