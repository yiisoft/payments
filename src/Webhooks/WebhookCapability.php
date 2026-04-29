<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Describes support for one normalized webhook capability.
 */
final readonly class WebhookCapability
{
    public function __construct(
        public WebhookEventType $eventType,
        public WebhookEntityKind $entityKind,
        public WebhookSupportStatus $supportStatus,
    ) {
    }
}
