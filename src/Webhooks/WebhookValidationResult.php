<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;

/**
 * Immutable result of provider-specific webhook request validation.
 */
final readonly class WebhookValidationResult
{
    public function __construct(
        public bool $isValid,
        public ?WebhookReason $reason = null,
    ) {
        if ($isValid && $reason !== null) {
            throw new InvalidArgumentException('Successful webhook validation result must not contain a failure reason.');
        }

        if (!$isValid && $reason === null) {
            throw new InvalidArgumentException('Failed webhook validation result must contain a failure reason.');
        }
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
    public static function failure(WebhookReason $reason): self
    {
        return new self(isValid: false, reason: $reason);
    }
}
