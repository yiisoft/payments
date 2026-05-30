<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific recognizer for Stripe payment webhook events.
 */
final readonly class WebhookStripeEventRecognizer implements WebhookEventRecognizerInterface
{
    /**
     * @var array<string, WebhookEventType>
     */
    private const array EVENT_TYPES = [
        'payment_intent.created' => WebhookEventType::PaymentCreated,
        'payment_intent.processing' => WebhookEventType::PaymentProcessing,
        'payment_intent.requires_action' => WebhookEventType::PaymentRequiresAction,
        'payment_intent.amount_capturable_updated' => WebhookEventType::PaymentRequiresCapture,
        'payment_intent.succeeded' => WebhookEventType::PaymentSucceeded,
        'payment_intent.payment_failed' => WebhookEventType::PaymentFailed,
        'payment_intent.canceled' => WebhookEventType::PaymentCanceled,
        'charge.refunded' => WebhookEventType::PaymentRefunded,
    ];

    public function recognizeProviderEventType(WebhookInput $input): ?string
    {
        $payload = json_decode($input->rawBody, true);

        if (!is_array($payload) || !isset($payload['type']) || !is_string($payload['type'])) {
            return null;
        }

        return $payload['type'];
    }

    public function recognizeEventType(string $providerEventType): ?WebhookEventType
    {
        return self::EVENT_TYPES[$providerEventType] ?? null;
    }
}
