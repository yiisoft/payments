<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;

/**
 * Human-readable explanation for a webhook processing outcome.
 */
final readonly class WebhookReason
{
    public function __construct(
        public WebhookReasonCode $code,
        public string $message,
        public ?string $providerEventType = null,
    ) {
        if (trim($message) === '') {
            throw new InvalidArgumentException('Webhook reason message must be a non-empty string.');
        }

        if ($providerEventType !== null && trim($providerEventType) === '') {
            throw new InvalidArgumentException('Webhook provider event type must be null or a non-empty string.');
        }
    }
}
