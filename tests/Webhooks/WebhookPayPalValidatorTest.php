<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalValidator;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;

final class WebhookPayPalValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = new WebhookPayPalValidator();

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('paypal', $validator->getProviderId());
    }

    public function testReturnsValidationFailureUntilPayPalValidationIsImplemented(): void
    {
        $result = (new WebhookPayPalValidator())->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $this->requiredTransmissionHeaders(),
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_webhook_validation_not_implemented', $result->reason->code->value);
        $this->assertSame('PayPal webhook validation is not implemented yet.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testReturnsValidationFailureWhenRequiredTransmissionHeaderIsMissing(): void
    {
        $headers = $this->requiredTransmissionHeaders();
        unset($headers['PayPal-Transmission-Sig']);

        $result = (new WebhookPayPalValidator())->validate(new WebhookInput(
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

        $result = (new WebhookPayPalValidator())->validate(new WebhookInput(
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

    public function testRequiredTransmissionHeadersAreReadCaseInsensitively(): void
    {
        $result = (new WebhookPayPalValidator())->validate(new WebhookInput(
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
        $this->assertSame('paypal_webhook_validation_not_implemented', $result->reason->code->value);
    }

    public function testRequiredTransmissionHeadersMayUseMultiValueHeaders(): void
    {
        $headers = $this->requiredTransmissionHeaders();
        $headers['PayPal-Transmission-Sig'] = ['', 'signature'];

        $result = (new WebhookPayPalValidator())->validate(new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $headers,
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_webhook_validation_not_implemented', $result->reason->code->value);
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
