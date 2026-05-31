<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific mapper skeleton for YooKassa payment webhook payloads.
 */
final readonly class WebhookYooKassaPaymentMapper implements WebhookPaymentMapperInterface
{
    public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult
    {
        if ($payload->eventType === null) {
            return WebhookProcessingResult::unknownEvent($payload->providerEventType, $payload->rawData);
        }

        if (WebhookPaymentOutcomeRules::shouldProcess($payload->eventType)) {
            return WebhookProcessingResult::processed(
                $payload->eventType,
                $payload->rawData,
                $this->extractPaymentStatus($payload),
            );
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

        $object = $payload->data['object'] ?? null;

        if (!is_array($object)) {
            return null;
        }

        $status = $object['status'] ?? null;

        return is_string($status) ? $status : null;
    }
}
