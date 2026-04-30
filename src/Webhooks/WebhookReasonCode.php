<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;

/**
 * Machine-readable reason code for a webhook processing outcome.
 */
final readonly class WebhookReasonCode implements \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Webhook reason code must be a non-empty string.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
