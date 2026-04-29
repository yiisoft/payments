<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific validator skeleton for Robokassa ResultURL callbacks.
 *
 * R1 supports the classic Robokassa ResultURL callback format described by
 * {@see WebhookRobokassaCallbackFormat}. The skeleton intentionally stays
 * fail-closed until required parameter validation and password2 signature
 * verification are added by the following atomic tasks.
 */
final readonly class WebhookRobokassaValidator implements WebhookProviderValidatorInterface
{
    public function getProviderId(): string
    {
        return WebhookRobokassaCallbackFormat::PROVIDER_ID;
    }

    public function validate(WebhookInput $input): WebhookValidationResult
    {
        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode('robokassa_webhook_validation_not_implemented'),
            message: 'Robokassa webhook validation is not implemented yet.',
        ));
    }
}
