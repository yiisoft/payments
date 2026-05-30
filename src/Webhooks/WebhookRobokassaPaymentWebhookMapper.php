<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific mapper for Robokassa payment webhook payloads.
 */
final readonly class WebhookRobokassaPaymentWebhookMapper implements PaymentWebhookMapperInterface
{
    public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult
    {
        if ($payload->eventType === null) {
            return WebhookProcessingResult::unknownEvent($payload->providerEventType ?? '');
        }

        if ($payload->eventType === WebhookEventType::PaymentSucceeded) {
            return WebhookProcessingResult::processed($payload->eventType, $payload->rawData);
        }

        return WebhookProcessingResult::unsupportedEvent(
            $payload->eventType,
            $payload->providerEventType,
            $payload->rawData,
        );
    }

    public function extractPaymentStatus(WebhookPayload $payload): ?string
    {
        if ($payload->paymentStatus !== null) {
            return $payload->paymentStatus;
        }

        /**
         * Robokassa ResultURL does not carry a separate payment status field. R1 treats only a
         * successfully recognized ResultURL callback as the provider status signal. Missing,
         * ambiguous, or unsupported callback formats remain unmapped and are represented as null.
         */
        if (
            $payload->eventType === WebhookEventType::PaymentSucceeded
            && $payload->providerEventType === WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL
        ) {
            return WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL;
        }

        return null;
    }
}
