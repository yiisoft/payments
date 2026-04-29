<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific validator skeleton for Robokassa ResultURL callbacks.
 *
 * R1 supports the classic Robokassa ResultURL callback format described by
 * {@see WebhookRobokassaCallbackFormat}. This validator currently checks only
 * required callback parameters. It intentionally stays fail-closed until
 * password2 signature verification is added by the following atomic task.
 */
final readonly class WebhookRobokassaValidator implements WebhookProviderValidatorInterface
{
    public function getProviderId(): string
    {
        return WebhookRobokassaCallbackFormat::PROVIDER_ID;
    }

    public function validate(WebhookInput $input): WebhookValidationResult
    {
        $callbackParams = $input->queryParams + $input->bodyParams;

        foreach (WebhookRobokassaCallbackFormat::requiredParameters() as $parameterName) {
            if (
                !array_key_exists($parameterName, $callbackParams)
                || !is_string($callbackParams[$parameterName])
                || trim($callbackParams[$parameterName]) === ''
            ) {
                return WebhookValidationResult::failure(new WebhookReason(
                    code: new WebhookReasonCode('robokassa_required_parameter_missing'),
                    message: sprintf('Required Robokassa callback parameter "%s" is missing or empty.', $parameterName),
                ));
            }
        }

        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode('robokassa_webhook_validation_not_implemented'),
            message: 'Robokassa webhook validation is not implemented yet.',
        ));
    }
}
