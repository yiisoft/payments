<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Defines R1-normalized payment outcome boundaries for recognized webhook events.
 */
final readonly class WebhookPaymentOutcomeRules
{
    /**
     * Returns normalized payment outcomes that belong to R1 payment webhook processing.
     *
     * Refund-like events are recognized for explicit unsupported handling, but refund
     * normalization itself is intentionally reserved for a later release.
     *
     * @return list<WebhookEventType>
     */
    public static function processedPaymentOutcomes(): array
    {
        return [
            WebhookEventType::PaymentCreated,
            WebhookEventType::PaymentProcessing,
            WebhookEventType::PaymentRequiresAction,
            WebhookEventType::PaymentRequiresCapture,
            WebhookEventType::PaymentSucceeded,
            WebhookEventType::PaymentFailed,
            WebhookEventType::PaymentCanceled,
        ];
    }

    /**
     * Returns recognized webhook event types that must stay outside R1 normalization.
     *
     * @return list<WebhookEventType>
     */
    public static function unsupportedPaymentOutcomes(): array
    {
        return [
            WebhookEventType::PaymentRefunded,
        ];
    }

    /**
     * Returns whether a recognized event type should produce a processed R1 payment result.
     */
    public static function shouldProcess(WebhookEventType $eventType): bool
    {
        return in_array($eventType, self::processedPaymentOutcomes(), true);
    }

    /**
     * Returns whether a recognized event type is intentionally outside R1 payment normalization.
     */
    public static function shouldRejectAsUnsupported(WebhookEventType $eventType): bool
    {
        return in_array($eventType, self::unsupportedPaymentOutcomes(), true);
    }
}
