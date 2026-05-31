<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Verifies a PayPal webhook request signature against the configured webhook ID.
 */
interface WebhookPayPalSignatureVerifierInterface
{
    public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult;
}
