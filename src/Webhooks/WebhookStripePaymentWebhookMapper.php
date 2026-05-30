<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific mapper skeleton for Stripe payment webhook payloads.
 */
final readonly class WebhookStripePaymentWebhookMapper implements PaymentWebhookMapperInterface
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

        $object = $payload->data['data']['object'] ?? null;

        if (!is_array($object)) {
            return null;
        }

        $status = $object['status'] ?? null;

        return is_string($status) ? $status : null;
    }
}
