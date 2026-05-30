<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific mapper skeleton for Robokassa payment webhook payloads.
 */
final readonly class WebhookRobokassaPaymentWebhookMapper implements PaymentWebhookMapperInterface
{
    public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult
    {
        if ($payload->eventType === null) {
            return WebhookProcessingResult::unknownEvent($payload->providerEventType ?? '');
        }

        return WebhookProcessingResult::unsupportedEvent(
            $payload->eventType,
            $payload->providerEventType,
            $payload->rawData,
        );
    }

    public function extractPaymentStatus(WebhookPayload $payload): ?string
    {
        return null;
    }
}
