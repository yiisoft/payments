<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;

/**
 * Provider-specific validator for Stripe webhook requests.
 */
final readonly class WebhookStripeValidator implements WebhookProviderValidatorInterface
{
    public function __construct(
        private string $signingSecret,
    ) {
        if (trim($signingSecret) === '') {
            throw new InvalidArgumentException('Stripe webhook signing secret must be a non-empty string.');
        }
    }

    public function getProviderId(): string
    {
        return 'stripe';
    }

    public function validate(WebhookInput $input): WebhookValidationResult
    {
        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode('stripe_signature_validation_not_implemented'),
            message: 'Stripe webhook signature validation is not implemented yet.',
        ));
    }
}
