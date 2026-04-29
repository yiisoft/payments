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
        $signatureHeaders = $input->getHeader('Stripe-Signature');

        if ($signatureHeaders === []) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('stripe_signature_header_missing'),
                message: 'Stripe-Signature header is missing.',
            ));
        }

        $timestamp = null;
        $signatures = [];

        foreach ($signatureHeaders as $signatureHeader) {
            foreach (explode(',', $signatureHeader) as $part) {
                $part = trim($part);

                if ($part === '' || !str_contains($part, '=')) {
                    return WebhookValidationResult::failure(new WebhookReason(
                        code: new WebhookReasonCode('stripe_signature_header_malformed'),
                        message: 'Stripe-Signature header is malformed.',
                    ));
                }

                [$name, $value] = explode('=', $part, 2);
                $name = trim($name);
                $value = trim($value);

                if ($name === '' || $value === '') {
                    return WebhookValidationResult::failure(new WebhookReason(
                        code: new WebhookReasonCode('stripe_signature_header_malformed'),
                        message: 'Stripe-Signature header is malformed.',
                    ));
                }

                if ($name === 't') {
                    $timestamp = $value;
                    continue;
                }

                if ($name === 'v1') {
                    $signatures[] = $value;
                }
            }
        }

        if ($timestamp === null) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('stripe_signature_timestamp_missing'),
                message: 'Stripe-Signature header does not contain a timestamp.',
            ));
        }

        if (!ctype_digit($timestamp)) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('stripe_signature_timestamp_invalid'),
                message: 'Stripe-Signature header timestamp must be an integer.',
            ));
        }

        if ($signatures === []) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('stripe_signature_missing'),
                message: 'Stripe-Signature header does not contain a v1 signature.',
            ));
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $input->rawBody, $this->signingSecret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return WebhookValidationResult::success();
            }
        }

        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode('stripe_signature_mismatch'),
            message: 'Stripe webhook signature does not match the request payload.',
        ));
    }
}
