<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookStripeValidator;

final class WebhookStripeValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = new WebhookStripeValidator('whsec_test_secret');

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('stripe', $validator->getProviderId());
    }

    public function testSigningSecretMustBeNonEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stripe webhook signing secret must be a non-empty string.');

        new WebhookStripeValidator('   ');
    }

    public function testReturnsValidationFailureWhenSignatureHeaderIsMissing(): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded"}',
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('stripe_signature_header_missing', $result->reason->code->value);
        $this->assertSame('Stripe-Signature header is missing.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testReturnsValidationFailureWhenSignatureHeaderIsMalformed(): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded"}',
            headers: [
                'Stripe-Signature' => 't=1700000000,broken_part,v1=test_signature',
            ],
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('stripe_signature_header_malformed', $result->reason->code->value);
        $this->assertSame('Stripe-Signature header is malformed.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testReturnsValidationFailureWhenSignatureTimestampIsMissing(): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded"}',
            headers: [
                'Stripe-Signature' => 'v1=test_signature',
            ],
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('stripe_signature_timestamp_missing', $result->reason->code->value);
        $this->assertSame('Stripe-Signature header does not contain a timestamp.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testReturnsValidationFailureWhenSignatureTimestampIsInvalid(): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded"}',
            headers: [
                'Stripe-Signature' => 't=not-a-timestamp,v1=test_signature',
            ],
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('stripe_signature_timestamp_invalid', $result->reason->code->value);
        $this->assertSame('Stripe-Signature header timestamp must be an integer.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testReturnsValidationFailureWhenV1SignatureIsMissing(): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded"}',
            headers: [
                'Stripe-Signature' => 't=1700000000,v0=legacy_signature',
            ],
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('stripe_signature_missing', $result->reason->code->value);
        $this->assertSame('Stripe-Signature header does not contain a v1 signature.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testParsesSignatureHeaderCaseInsensitively(): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded"}',
            headers: [
                'stripe-signature' => 't=1700000000,v1=test_signature',
            ],
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('stripe_signature_validation_not_implemented', $result->reason->code->value);
        $this->assertSame('Stripe webhook signature validation is not implemented yet.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testParsesMultipleHeaderValues(): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded"}',
            headers: [
                'Stripe-Signature' => [
                    't=1700000000',
                    'v1=test_signature',
                ],
            ],
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('stripe_signature_validation_not_implemented', $result->reason->code->value);
        $this->assertSame('Stripe webhook signature validation is not implemented yet.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    private function validator(): WebhookStripeValidator
    {
        return new WebhookStripeValidator('whsec_test_secret');
    }
}
