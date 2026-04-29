<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific webhook processor registered under a stable provider identifier.
 */
interface WebhookProviderProcessorInterface extends WebhookProcessorInterface
{
    public function getProviderId(): string;
}
