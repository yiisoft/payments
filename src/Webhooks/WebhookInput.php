<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Immutable input object created from an incoming webhook request.
 */
final readonly class WebhookInput
{
    /**
     * @param array<string, list<string>> $headers
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
}
