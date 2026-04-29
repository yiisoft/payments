<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Immutable result of provider-specific webhook request validation.
 */
final readonly class WebhookValidationResult
{
    public function __construct(
        public bool $isValid,
    ) {
    }
}
