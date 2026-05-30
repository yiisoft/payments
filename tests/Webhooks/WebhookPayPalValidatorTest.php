<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalSignatureVerifierInterface;
use Yiisoft\Payments\Webhooks\WebhookPayPalValidator;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;

final class WebhookPayPalValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = new WebhookPayPalValidator(self::successfulVerifier(), 'WH-123');

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('paypal', $validator->getProviderId());
    }

    public function testRejectsEmptyWebhookId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PayPal webhook ID must be a non-empty string.');

        new WebhookPayPalValidator(self::successfulVerifier(), '   ');
    }

    public function testDelegatesStructurallyValidPayPalValidationInputToVerifier(): void
    {
        $result = (new WebhookPayPalValidator(self::successfulVerifier(), 'WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-EVENT-123","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAPTURE-123"}}',
            headers: $this->requiredTransmissionHeaders(),
            providerId: 'paypal',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testDelegatesStructurallyValidPayPalValidationInputWithWhitespaceAroundHeaderValuesToVerifier(): void
    {
        $headers = $this->requiredTransmissionHeaders();
        $headers['PayPal-Transmission-Id'] = '  transmission-id  ';
        $headers['PayPal-Transmission-Time'] = '  2026-04-29T10:00:00Z  ';
        $headers['PayPal-Cert-Url'] = '  https://api-m.paypal.com/certs/test.pem  ';
        $headers['PayPal-Auth-Algo'] = '  SHA256withRSA  ';
        $headers['PayPal-Transmission-Sig'] = '  signature  ';

        $result = (new WebhookPayPalValidator(self::successfulVerifier(), 'WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-EVENT-123","event_type":"CHECKOUT.ORDER.APPROVED"}',
            headers: $headers,
            providerId: 'paypal',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testReturnsVerifierFailure(): void
    {
        $result = (new WebhookPayPalValidator(self::failingVerifier(), 'WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $this->requiredTransmissionHeaders(),
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_signature_verification_failed', $result->reason->code->value);
        $this->assertSame('PayPal webhook signature verification failed.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testPassesConfiguredWebhookIdToVerifier(): void
    {
        $verifier = new class implements WebhookPayPalSignatureVerifierInterface {
            public ?string $webhookId = null;

            public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
            {
                $this->webhookId = $webhookId;

                return WebhookValidationResult::success();
            }
        };

        $result = (new WebhookPayPalValidator($verifier, 'WH-CONFIGURED-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $this->requiredTransmissionHeaders(),
            providerId: 'paypal',
        ));

        $this->assertTrue($result->isValid);
        $this->assertSame('WH-CONFIGURED-123', $verifier->webhookId);
    }

    public function testReturnsValidationFailureWhenRequiredTransmissionHeaderIsMissing(): void
    {
        $headers = $this->requiredTransmissionHeaders();
        unset($headers['PayPal-Transmission-Sig']);

        $result = (new WebhookPayPalValidator(self::successfulVerifier(), 'WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $headers,
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_required_transmission_header_missing', $result->reason->code->value);
        $this->assertSame(
            'Required PayPal transmission header "PayPal-Transmission-Sig" is missing or empty.',
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }

    public function testReturnsValidationFailureWhenRequiredTransmissionHeaderIsEmpty(): void
    {
        $headers = $this->requiredTransmissionHeaders();
        $headers['PayPal-Transmission-Id'] = '   ';

        $result = (new WebhookPayPalValidator(self::successfulVerifier(), 'WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $headers,
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_required_transmission_header_missing', $result->reason->code->value);
        $this->assertSame(
            'Required PayPal transmission header "PayPal-Transmission-Id" is missing or empty.',
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }

    public function testReturnsValidationFailureForEachMissingRequiredTransmissionHeader(): void
    {
        foreach (array_keys($this->requiredTransmissionHeaders()) as $headerName) {
            $headers = $this->requiredTransmissionHeaders();
            unset($headers[$headerName]);

            $result = (new WebhookPayPalValidator(self::successfulVerifier(), 'WH-123'))->validate(new WebhookInput(
                rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
                headers: $headers,
                providerId: 'paypal',
            ));

            $this->assertFalse($result->isValid, sprintf('Header %s must be required.', $headerName));
            $this->assertNotNull($result->reason);
            $this->assertSame('paypal_required_transmission_header_missing', $result->reason->code->value);
            $this->assertSame(
                sprintf('Required PayPal transmission header "%s" is missing or empty.', $headerName),
                $result->reason->message,
            );
            $this->assertNull($result->reason->providerEventType);
        }
    }

    public function testReturnsValidationFailureForEachEmptyRequiredTransmissionHeader(): void
    {
        foreach (array_keys($this->requiredTransmissionHeaders()) as $headerName) {
            $headers = $this->requiredTransmissionHeaders();
            $headers[$headerName] = ['', "   \t"];

            $result = (new WebhookPayPalValidator(self::successfulVerifier(), 'WH-123'))->validate(new WebhookInput(
                rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
                headers: $headers,
                providerId: 'paypal',
            ));

            $this->assertFalse($result->isValid, sprintf('Header %s must not be empty.', $headerName));
            $this->assertNotNull($result->reason);
            $this->assertSame('paypal_required_transmission_header_missing', $result->reason->code->value);
            $this->assertSame(
                sprintf('Required PayPal transmission header "%s" is missing or empty.', $headerName),
                $result->reason->message,
            );
            $this->assertNull($result->reason->providerEventType);
        }
    }

    public function testRejectsWhitespaceOnlyWebhookIdBeforeValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PayPal webhook ID must be a non-empty string.');

        new WebhookPayPalValidator(self::successfulVerifier(), " \t\n ");
    }

    public function testRequiredTransmissionHeadersAreReadCaseInsensitively(): void
    {
        $result = (new WebhookPayPalValidator(self::successfulVerifier(), 'WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: [
                'paypal-transmission-id' => 'transmission-id',
                'paypal-transmission-time' => '2026-04-29T10:00:00Z',
                'paypal-cert-url' => 'https://api-m.paypal.com/certs/test.pem',
                'paypal-auth-algo' => 'SHA256withRSA',
                'paypal-transmission-sig' => 'signature',
            ],
            providerId: 'paypal',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testRequiredTransmissionHeadersMayUseMultiValueHeaders(): void
    {
        $headers = $this->requiredTransmissionHeaders();
        $headers['PayPal-Transmission-Sig'] = ['', 'signature'];

        $result = (new WebhookPayPalValidator(self::successfulVerifier(), 'WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $headers,
            providerId: 'paypal',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function requiredTransmissionHeaders(): array
    {
        return [
            'PayPal-Transmission-Id' => 'transmission-id',
            'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
            'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/test.pem',
            'PayPal-Auth-Algo' => 'SHA256withRSA',
            'PayPal-Transmission-Sig' => 'signature',
        ];
    }

    private static function successfulVerifier(): WebhookPayPalSignatureVerifierInterface
    {
        return new class implements WebhookPayPalSignatureVerifierInterface {
            public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
            {
                return WebhookValidationResult::success();
            }
        };
    }

    private static function failingVerifier(): WebhookPayPalSignatureVerifierInterface
    {
        return new class implements WebhookPayPalSignatureVerifierInterface {
            public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
            {
                return WebhookValidationResult::failure(new WebhookReason(
                    code: new WebhookReasonCode('paypal_signature_verification_failed'),
                    message: 'PayPal webhook signature verification failed.',
                ));
            }
        };
    }
}
