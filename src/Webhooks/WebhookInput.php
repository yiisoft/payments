<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Immutable input object created from an incoming webhook request.
 */
final readonly class WebhookInput
{
    public function __construct(
        public string $rawBody,
    ) {
    }
}
