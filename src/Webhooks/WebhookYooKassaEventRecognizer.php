<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific recognizer for YooKassa payment webhook events.
 */
final readonly class WebhookYooKassaEventRecognizer implements WebhookEventRecognizerInterface
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
