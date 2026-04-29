<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Raw webhook data preserved for diagnostics and provider-specific processing.
 */
final readonly class WebhookRawData
{
    public function __construct(
        public string $rawBody,
    ) {
    }
}
