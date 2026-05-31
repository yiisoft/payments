<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Declares normalized webhook capabilities supported by a payment gateway.
 */
interface WebhookCapabilitiesProviderInterface
{
    public function getWebhookCapabilities(): WebhookCapabilities;
}
