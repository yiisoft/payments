<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific webhook processor registered under a stable provider identifier.
 */
interface WebhookProviderProcessorInterface
{
    public function getProviderId(): string;

    public function process(WebhookInput $input): WebhookProcessingResult;
}
