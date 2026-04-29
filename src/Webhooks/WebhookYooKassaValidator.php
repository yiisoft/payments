<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific validator skeleton for YooKassa webhook requests.
 */
final readonly class WebhookYooKassaValidator implements WebhookProviderValidatorInterface
{
    public function getProviderId(): string
    {
        return 'yookassa';
    }

    public function validate(WebhookInput $input): WebhookValidationResult
    {
        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode('yookassa_webhook_validation_not_implemented'),
            message: 'YooKassa webhook validation is not implemented yet.',
        ));
    }
}
