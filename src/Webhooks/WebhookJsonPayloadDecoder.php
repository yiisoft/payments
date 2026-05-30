<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Decodes JSON webhook payloads without throwing on malformed input.
 */
final class WebhookJsonPayloadDecoder
{
    /**
     * @return array<string, mixed>
     */
    public function decode(string $rawBody): array
    {
        $payload = json_decode($rawBody, true);

        return is_array($payload) ? $payload : [];
    }
}
