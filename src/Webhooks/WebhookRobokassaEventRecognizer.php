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
        if ($this->hasConflictingCallbackParameter($input->queryParams, $input->bodyParams)) {
            return null;
        }

        $callbackParams = $input->queryParams + $input->bodyParams;

        foreach (WebhookRobokassaCallbackFormat::requiredParameters() as $parameterName) {
            if (
                !array_key_exists($parameterName, $callbackParams)
                || !is_string($callbackParams[$parameterName])
                || trim($callbackParams[$parameterName]) === ''
            ) {
                return null;
            }
        }

        return WebhookRobokassaCallbackFormat::CALLBACK_TYPE;
    }

    public function recognizeEventType(string $providerEventType): ?WebhookEventType
    {
        return $providerEventType === WebhookRobokassaCallbackFormat::CALLBACK_TYPE
            ? WebhookRobokassaCallbackFormat::supportedR1PaymentOutcome()
            : null;
    }

    /**
     * @param array<string, mixed> $queryParams Original Robokassa provider fields from the query string.
     * @param array<string, mixed> $bodyParams Original Robokassa provider fields from the form body.
     */
    private function hasConflictingCallbackParameter(array $queryParams, array $bodyParams): bool
    {
        foreach ($queryParams as $parameterName => $queryValue) {
            if (
                !array_key_exists($parameterName, $bodyParams)
                || !WebhookRobokassaCallbackFormat::isRequiredParameter($parameterName)
            ) {
                continue;
            }

            if ($queryValue !== $bodyParams[$parameterName]) {
                return true;
            }
        }

        return false;
    }
}
