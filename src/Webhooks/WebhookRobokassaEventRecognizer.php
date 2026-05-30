<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific recognizer for Robokassa payment callback events.
 */
final readonly class WebhookRobokassaEventRecognizer implements WebhookEventRecognizerInterface
{
    public function recognizeProviderEventType(WebhookInput $input): ?string
    {
        $callbackParams = $input->queryParams + $input->bodyParams;

        foreach (WebhookRobokassaCallbackFormat::requiredParameters() as $parameterName) {
            if (!array_key_exists($parameterName, $callbackParams)) {
                return null;
            }
        }

        return WebhookRobokassaCallbackFormat::CALLBACK_TYPE;
    }

    public function recognizeEventType(string $providerEventType): ?WebhookEventType
    {
        return $providerEventType === WebhookRobokassaCallbackFormat::CALLBACK_TYPE
            ? WebhookEventType::PaymentSucceeded
            : null;
    }
}
