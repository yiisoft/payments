<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;

/**
 * Provider-specific validator for PayPal webhook requests.
 *
 * R1 checks the local validation preconditions required for PayPal signature
 * verification, then delegates provider authenticity verification to the
 * configured signature verifier.
 */
final readonly class WebhookPayPalValidator implements WebhookProviderValidatorInterface
{
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

    public function __construct(
        private WebhookPayPalSignatureVerifierInterface $signatureVerifier,
        private string $webhookId,
    ) {
        if (trim($webhookId) === '') {
            throw new InvalidArgumentException('PayPal webhook ID must be a non-empty string.');
        }
    }

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

        return $this->signatureVerifier->verify($input, $this->webhookId);
    }
}
