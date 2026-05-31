<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific mapper of parsed payment webhook payloads into a normalized processing result.
 */
interface WebhookPaymentMapperInterface
{
    /**
     * Maps an intermediate provider payment webhook payload into the common processing outcome.
     */
    public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult;

    /**
     * Extracts the minimal R1 payment status representation from the intermediate payload.
     *
     * This method is the common status extraction hook for payment webhook mappers. R1 keeps
     * the representation intentionally minimal: implementations return a provider status string
     * when it is already available in the parsed payload and return null when the status is not
     * available or is not safely mappable at this stage.
     *
     * The method must not derive an application-level payment state, must not create an
     * artificial unknown sentinel, and must not require a dedicated common status value object.
     * Provider-specific mapping rules can be added by concrete mappers without changing this
     * method signature.
     */
    public function extractPaymentStatus(WebhookPayload $payload): ?string;
}
