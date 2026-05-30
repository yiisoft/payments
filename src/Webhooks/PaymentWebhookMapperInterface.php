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
     * Extracts the minimal R1 payment status representation from the intermediate payload.
     *
     * R1 intentionally returns the provider status as a nullable string instead of introducing
     * a dedicated common status value object. This keeps the webhook contract aligned with
     * the existing PaymentIntent status surface and avoids a premature domain model.
     *
     * When the provider status cannot be extracted or cannot be represented by the minimal
     * R1 contract, implementations must return null instead of inventing an unknown status value.
     */
    public function extractPaymentStatus(WebhookPayload $payload): ?string;
}
