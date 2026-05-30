<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific parser of incoming webhook request data into an intermediate payload.
 */
interface WebhookPayloadParserInterface
{
    public function parsePayload(
        WebhookInput $input,
        WebhookEventType $eventType,
        ?string $providerEventType = null,
    ): WebhookPayload;
}
