<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Public entry point for processing an incoming webhook input.
 */
interface WebhookProcessorInterface
{
    public function process(WebhookInput $input): WebhookContext;
}
