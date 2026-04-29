<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalValidator;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;

final class WebhookPayPalValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = new WebhookPayPalValidator('WH-123');

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('paypal', $validator->getProviderId());
    }

    public function testRejectsEmptyWebhookId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PayPal webhook ID must be a non-empty string.');

        new WebhookPayPalValidator('   ');
    }

    public function testAcceptsStructurallyValidPayPalValidationInput(): void
    {
        $result = (new WebhookPayPalValidator('WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-EVENT-123","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAPTURE-123"}}',
            headers: $this->requiredTransmissionHeaders(),
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_live_verification_not_supported_in_r1', $result->reason->code->value);
        $this->assertSame(
            'PayPal webhook validation in R1 does not perform live certificate or PayPal API verification.',
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }

    public function testAcceptsStructurallyValidPayPalValidationInputWithWhitespaceAroundHeaderValues(): void
    {
        $headers = $this->requiredTransmissionHeaders();
        $headers['PayPal-Transmission-Id'] = '  transmission-id  ';
        $headers['PayPal-Transmission-Time'] = '  2026-04-29T10:00:00Z  ';
        $headers['PayPal-Cert-Url'] = '  https://api-m.paypal.com/certs/test.pem  ';
        $headers['PayPal-Auth-Algo'] = '  SHA256withRSA  ';
        $headers['PayPal-Transmission-Sig'] = '  signature  ';

        $result = (new WebhookPayPalValidator('WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-EVENT-123","event_type":"CHECKOUT.ORDER.APPROVED"}',
            headers: $headers,
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_live_verification_not_supported_in_r1', $result->reason->code->value);
    }

    public function testReturnsValidationFailureForR1LimitationWithoutLiveVerification(): void
    {
        $result = (new WebhookPayPalValidator('WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $this->requiredTransmissionHeaders(),
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_live_verification_not_supported_in_r1', $result->reason->code->value);
        $this->assertSame(
            'PayPal webhook validation in R1 does not perform live certificate or PayPal API verification.',
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }

    public function testReturnsValidationFailureWhenRequiredTransmissionHeaderIsMissing(): void
    {
        $headers = $this->requiredTransmissionHeaders();
        unset($headers['PayPal-Transmission-Sig']);

        $result = (new WebhookPayPalValidator('WH-123'))->validate(new WebhookInput(
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

        $result = (new WebhookPayPalValidator('WH-123'))->validate(new WebhookInput(
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

            $result = (new WebhookPayPalValidator('WH-123'))->validate(new WebhookInput(
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

            $result = (new WebhookPayPalValidator('WH-123'))->validate(new WebhookInput(
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

        new WebhookPayPalValidator(" \t\n ");
    }

    public function testRequiredTransmissionHeadersAreReadCaseInsensitively(): void
    {
        $result = (new WebhookPayPalValidator('WH-123'))->validate(new WebhookInput(
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

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_live_verification_not_supported_in_r1', $result->reason->code->value);
    }

    public function testRequiredTransmissionHeadersMayUseMultiValueHeaders(): void
    {
        $headers = $this->requiredTransmissionHeaders();
        $headers['PayPal-Transmission-Sig'] = ['', 'signature'];

        $result = (new WebhookPayPalValidator('WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $headers,
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_live_verification_not_supported_in_r1', $result->reason->code->value);
    }

    public function testDoesNotExposeSuccessWithoutExplicitLiveVerificationSupport(): void
    {
        $result = (new WebhookPayPalValidator('WH-123'))->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $this->requiredTransmissionHeaders(),
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_live_verification_not_supported_in_r1', $result->reason->code->value);
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
}
