<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific recognizer for PayPal payment webhook events.
 */
final readonly class WebhookPayPalEventRecognizer implements WebhookEventRecognizerInterface
{
    /**
     * @var array<string, WebhookEventType>
     */
    private const array EVENT_TYPES = [
        'CHECKOUT.ORDER.APPROVED' => WebhookEventType::PaymentRequiresCapture,
        'CHECKOUT.PAYMENT-APPROVAL.REVERSED' => WebhookEventType::PaymentCanceled,
        'PAYMENT.AUTHORIZATION.CREATED' => WebhookEventType::PaymentRequiresCapture,
        'PAYMENT.CAPTURE.PENDING' => WebhookEventType::PaymentProcessing,
        'PAYMENT.CAPTURE.COMPLETED' => WebhookEventType::PaymentSucceeded,
        'PAYMENT.CAPTURE.DENIED' => WebhookEventType::PaymentFailed,
        'PAYMENT.CAPTURE.DECLINED' => WebhookEventType::PaymentFailed,
        'PAYMENT.CAPTURE.REFUNDED' => WebhookEventType::PaymentRefunded,
        'PAYMENT.CAPTURE.REVERSED' => WebhookEventType::PaymentRefunded,
    ];

    public function recognizeProviderEventType(WebhookInput $input): ?string
    {
        $payload = json_decode($input->rawBody, true);

        if (!is_array($payload) || !isset($payload['event_type']) || !is_string($payload['event_type'])) {
            return null;
        }

        return $payload['event_type'];
    }

    public function recognizeEventType(string $providerEventType): ?WebhookEventType
    {
        return self::EVENT_TYPES[$providerEventType] ?? null;
    }
}
