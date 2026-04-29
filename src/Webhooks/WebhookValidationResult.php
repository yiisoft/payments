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

    /**
     * Creates a result for a successfully validated webhook request.
     */
    public static function success(): self
    {
        return new self(isValid: true);
    }

    /**
     * Creates a result for a webhook request that failed provider-specific validation.
     */
    public static function failure(): self
    {
        return new self(isValid: false);
    }
}
