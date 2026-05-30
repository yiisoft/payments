<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific mapper skeleton for PayPal payment webhook payloads.
 */
final readonly class WebhookPayPalPaymentWebhookMapper implements PaymentWebhookMapperInterface
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

        $resource = $payload->data['resource'] ?? null;

        if (!is_array($resource)) {
            return null;
        }

        $status = $resource['status'] ?? null;

        return is_string($status) ? $status : null;
    }
}
