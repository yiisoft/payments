<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific validator skeleton for PayPal webhook requests.
 */
final readonly class WebhookPayPalValidator implements WebhookProviderValidatorInterface
{
    public function getProviderId(): string
    {
        return 'paypal';
    }

    public function validate(WebhookInput $input): WebhookValidationResult
    {
        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode('paypal_webhook_validation_not_implemented'),
            message: 'PayPal webhook validation is not implemented yet.',
        ));
    }
}
