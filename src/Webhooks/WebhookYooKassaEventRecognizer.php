<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific recognizer for YooKassa payment webhook events.
 */
final readonly class WebhookYooKassaEventRecognizer implements WebhookEventRecognizerInterface
{
    /**
     * @var array<string, WebhookEventType>
     */
    private const array EVENT_TYPES = [
        'payment.waiting_for_capture' => WebhookEventType::PaymentRequiresCapture,
        'payment.succeeded' => WebhookEventType::PaymentSucceeded,
        'payment.canceled' => WebhookEventType::PaymentCanceled,
        'refund.succeeded' => WebhookEventType::PaymentRefunded,
    ];

    public function recognizeProviderEventType(WebhookInput $input): ?string
    {
        $payload = json_decode($input->rawBody, true);

        if (!is_array($payload) || !isset($payload['event']) || !is_string($payload['event'])) {
            return null;
        }

        return $payload['event'];
    }

    public function recognizeEventType(string $providerEventType): ?WebhookEventType
    {
        return self::EVENT_TYPES[$providerEventType] ?? null;
    }
}
