<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific recognizer for Stripe payment webhook events.
 */
final readonly class WebhookStripeEventRecognizer implements WebhookEventRecognizerInterface
{
    public function recognizeProviderEventType(WebhookInput $input): ?string
    {
        return null;
    }

    public function recognizeEventType(string $providerEventType): ?WebhookEventType
    {
        return null;
    }
}
