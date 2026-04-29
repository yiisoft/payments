<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific validator skeleton for YooKassa webhook requests.
 *
 * The current package configuration exposes YooKassa API credentials for
 * outgoing API requests, but it does not expose a webhook-specific signing
 * secret, shared callback token, certificate chain, signature header, or a
 * provider-side verification endpoint contract that can be used for local
 * authenticity validation. Until a concrete authenticity indicator is added to
 * the public configuration, this validator must stay fail-closed.
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
            code: new WebhookReasonCode('yookassa_authenticity_indicators_not_available'),
            message: 'YooKassa webhook validation cannot be completed because the current API/config does not expose a webhook-specific authenticity indicator.',
        ));
    }
}
