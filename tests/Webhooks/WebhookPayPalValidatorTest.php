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
            headers: [
                'PayPal-Transmission-Id' => 'transmission-id',
                'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
                'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/test.pem',
                'PayPal-Auth-Algo' => 'SHA256withRSA',
                'PayPal-Transmission-Sig' => 'signature',
            ],
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_webhook_validation_not_implemented', $result->reason->code->value);
        $this->assertSame('PayPal webhook validation is not implemented yet.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }
}
