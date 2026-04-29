<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;

/**
 * Provider-specific validator skeleton for PayPal webhook requests.
 *
 * R1 only checks the local validation preconditions required to identify a
 * PayPal webhook validation attempt. It intentionally does not perform live
 * certificate or PayPal API verification until that behavior is agreed and
 * introduced explicitly.
 */
final readonly class WebhookPayPalValidator implements WebhookProviderValidatorInterface
{
    private const R1_LIMITATION_REASON_CODE = 'paypal_live_verification_not_supported_in_r1';

    public function __construct(
        private string $webhookId,
    ) {
        if (trim($webhookId) === '') {
            throw new InvalidArgumentException('PayPal webhook ID must be a non-empty string.');
        }
    }

    /**
     * @var list<string>
     */
    private const REQUIRED_TRANSMISSION_HEADERS = [
        'PayPal-Transmission-Id',
        'PayPal-Transmission-Time',
        'PayPal-Cert-Url',
        'PayPal-Auth-Algo',
        'PayPal-Transmission-Sig',
    ];

    public function getProviderId(): string
    {
        return 'paypal';
    }

    public function validate(WebhookInput $input): WebhookValidationResult
    {
        foreach (self::REQUIRED_TRANSMISSION_HEADERS as $headerName) {
            $hasHeader = false;

            foreach ($input->getHeader($headerName) as $headerValue) {
                if (trim($headerValue) !== '') {
                    $hasHeader = true;
                    break;
                }
            }

            if (!$hasHeader) {
                return WebhookValidationResult::failure(new WebhookReason(
                    code: new WebhookReasonCode('paypal_required_transmission_header_missing'),
                    message: sprintf('Required PayPal transmission header "%s" is missing or empty.', $headerName),
                ));
            }
        }

        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode(self::R1_LIMITATION_REASON_CODE),
            message: 'PayPal webhook validation in R1 does not perform live certificate or PayPal API verification.',
        ));
    }
}
