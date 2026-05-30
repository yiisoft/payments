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
        return null;
    }
}
