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
     */
    public function __construct(
        public string $rawBody,
        public array $headers = [],
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
}
