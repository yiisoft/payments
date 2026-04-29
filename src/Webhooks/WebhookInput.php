<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Immutable input object created from an incoming webhook request.
 */
final readonly class WebhookInput
{
    /**
     * @param array<string, string|list<string>> $headers
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $bodyParams
     */
    public function __construct(
        public string $rawBody,
        public array $headers = [],
        public array $queryParams = [],
        public array $bodyParams = [],
        public ?string $providerId = null,
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
     * @return list<string>
     */
    public function getHeader(string $name): array
    {
        $normalizedName = strtolower($name);
        $values = [];

        foreach ($this->headers as $headerName => $headerValues) {
            if (strtolower($headerName) === $normalizedName) {
                array_push($values, ...$this->normalizeHeaderValues($headerValues));
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function normalizeHeaderValues(string|array $headerValues): array
    {
        return is_string($headerValues) ? [$headerValues] : array_values($headerValues);
    }
}
