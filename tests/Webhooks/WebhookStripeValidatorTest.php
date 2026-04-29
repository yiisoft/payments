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

    public function testTimestampToleranceMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stripe webhook timestamp tolerance must be a positive integer.');

        new WebhookStripeValidator('whsec_test_secret', 0);
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

    public function testReturnsValidationFailureWhenSignatureTimestampIsOutsideTolerance(): void
    {
        $rawBody = '{"id":"evt_123","type":"payment_intent.succeeded"}';
        $timestamp = '1699999699';
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, 'whsec_test_secret');

        $result = $this->validator()->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'Stripe-Signature' => 't=' . $timestamp . ',v1=' . $signature,
            ],
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('stripe_signature_timestamp_out_of_tolerance', $result->reason->code->value);
        $this->assertSame('Stripe-Signature header timestamp is outside the allowed tolerance.', $result->reason->message);
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
        $this->assertSame('stripe_signature_mismatch', $result->reason->code->value);
        $this->assertSame('Stripe webhook signature does not match the request payload.', $result->reason->message);
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
        $this->assertSame('stripe_signature_mismatch', $result->reason->code->value);
        $this->assertSame('Stripe webhook signature does not match the request payload.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testReturnsSuccessWhenSignatureMatchesPayload(): void
    {
        $rawBody = '{"id":"evt_123","type":"payment_intent.succeeded"}';
        $timestamp = '1700000000';
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, 'whsec_test_secret');

        $result = $this->validator()->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'Stripe-Signature' => 't=' . $timestamp . ',v1=' . $signature,
            ],
            providerId: 'stripe',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testReturnsValidationFailureWhenSignatureDoesNotMatchPayload(): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded"}',
            headers: [
                'Stripe-Signature' => 't=1700000000,v1=invalid_signature',
            ],
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('stripe_signature_mismatch', $result->reason->code->value);
        $this->assertSame('Stripe webhook signature does not match the request payload.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testReturnsSuccessWhenOneOfMultipleV1SignaturesMatchesPayload(): void
    {
        $rawBody = '{"id":"evt_123","type":"payment_intent.succeeded"}';
        $timestamp = '1700000000';
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, 'whsec_test_secret');

        $result = $this->validator()->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'Stripe-Signature' => 't=' . $timestamp . ',v1=invalid_signature,v1=' . $signature,
            ],
            providerId: 'stripe',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    private function validator(): WebhookStripeValidator
    {
        return new WebhookStripeValidator(
            signingSecret: 'whsec_test_secret',
            timestampToleranceSeconds: 300,
            currentTimestamp: 1700000000,
        );
    }
}
