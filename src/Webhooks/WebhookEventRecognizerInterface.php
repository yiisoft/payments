<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific recognizer of payment-related webhook event types.
 */
interface WebhookEventRecognizerInterface
{
    public function recognizeProviderEventType(WebhookInput $input): ?string;

    public function recognizeEventType(string $providerEventType): ?WebhookEventType;
}
