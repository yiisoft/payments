<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific mapper of parsed payment webhook payloads into a normalized processing result.
 */
interface PaymentWebhookMapperInterface
{
    /**
     * Maps an intermediate provider payment webhook payload into the common processing outcome.
     */
    public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult;

    /**
     * Extracts a provider payment status from the intermediate payload, if it is available.
     */
    public function extractPaymentStatus(WebhookPayload $payload): ?string;
}
