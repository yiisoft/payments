<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Raw webhook data preserved for diagnostics and provider-specific processing.
 */
final readonly class WebhookRawData
{
    /**
     * @param array<string, string|list<string>> $headers
     * @param mixed $payload Provider payload decoded by a later processing step, if available.
     */
    public function __construct(
        public string $rawBody,
        public array $headers = [],
        public mixed $payload = null,
    ) {
    }

    /**
     * Returns the original header map without normalizing header names or values.
     *
     * @return array<string, string|list<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Returns the provider payload decoded by a later processing step, if available.
     */
    public function getPayload(): mixed
    {
        return $this->payload;
    }
}
