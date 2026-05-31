<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalSignatureVerifierInterface;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;

final class WebhookPayPalSignatureVerifierInterfaceTest extends TestCase
{
    public function testVerifyAcceptsWebhookInputAndWebhookIdAndReturnsValidationResult(): void
    {
        $method = new ReflectionMethod(WebhookPayPalSignatureVerifierInterface::class, 'verify');
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        $this->assertCount(2, $parameters);
        $this->assertSame('input', $parameters[0]->getName());
        $this->assertFalse($parameters[0]->allowsNull());
        $this->assertSame(WebhookInput::class, $parameters[0]->getType()?->getName());
        $this->assertSame('webhookId', $parameters[1]->getName());
        $this->assertFalse($parameters[1]->allowsNull());
        $this->assertSame('string', $parameters[1]->getType()?->getName());
        $this->assertNotNull($returnType);
        $this->assertFalse($returnType->allowsNull());
        $this->assertSame(WebhookValidationResult::class, $returnType->getName());
    }

    public function testVerifierCanReturnSuccessfulSignatureVerificationResult(): void
    {
        $verifier = new SuccessfulPayPalSignatureVerifier();

        $result = $verifier->verify($this->input(), 'WH-123');

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
        $this->assertSame('WH-123', $verifier->verifiedWebhookId);
    }

    public function testVerifierCanReturnFailedSignatureVerificationResult(): void
    {
        $verifier = new FailedPayPalSignatureVerifier();

        $result = $verifier->verify($this->input(), 'WH-123');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_signature_verification_failed', $result->reason->code->value);
        $this->assertSame('PayPal webhook signature verification failed.', $result->reason->message);
    }

    private function input(): WebhookInput
    {
        return new WebhookInput(
            rawBody: '{"id":"WH-EVENT-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: [
                'PayPal-Transmission-Id' => 'transmission-id',
                'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
                'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/test.pem',
                'PayPal-Auth-Algo' => 'SHA256withRSA',
                'PayPal-Transmission-Sig' => 'signature',
            ],
            providerId: 'paypal',
        );
    }
}

final class SuccessfulPayPalSignatureVerifier implements WebhookPayPalSignatureVerifierInterface
{
    public ?string $verifiedWebhookId = null;

    public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
    {
        $this->verifiedWebhookId = $webhookId;

        return WebhookValidationResult::success();
    }
}

final class FailedPayPalSignatureVerifier implements WebhookPayPalSignatureVerifierInterface
{
    public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
    {
        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode('paypal_signature_verification_failed'),
            message: 'PayPal webhook signature verification failed.',
        ));
    }
}
