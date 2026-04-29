<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use LogicException;

/**
 * Common webhook processing service that owns provider processor resolution flow.
 */
final class WebhookProcessor implements WebhookProcessorInterface
{
    public function __construct(
        private readonly WebhookProviderProcessorRegistry $providerProcessorRegistry,
    ) {
    }

    public function process(WebhookInput $input): WebhookProcessingResult
    {
        throw new LogicException('Webhook processing flow is not implemented yet.');
    }
}
