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
     * @param string|null $providerEventType Provider-specific event type extracted by a later processing step, if available.
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $bodyParams
     */
    public function __construct(
        public string $rawBody,
        public array $headers = [],
        public mixed $payload = null,
        public ?string $providerEventType = null,
        public array $queryParams = [],
        public array $bodyParams = [],
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
     * Returns the original query parameter map without normalizing names or values.
     *
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Returns the original body parameter map without normalizing names or values.
     *
     * @return array<string, mixed>
     */
    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    /**
     * Returns the provider payload decoded by a later processing step, if available.
     */
    public function getPayload(): mixed
    {
        return $this->payload;
    }

    /**
     * Returns the provider-specific event type extracted by a later processing step, if available.
     */
    public function getProviderEventType(): ?string
    {
        return $this->providerEventType;
    }
}
