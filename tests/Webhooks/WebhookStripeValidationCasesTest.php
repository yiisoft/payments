<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookStripeValidator;

final class WebhookStripeValidationCasesTest extends TestCase
{
    /**
     * @dataProvider validSignatureProvider
     */
    public function testAcceptsValidSignatureCases(string $rawBody, array $headers): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: $headers,
            providerId: 'stripe',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    /**
     * @return iterable<string, array{string, array<string, string|list<string>>}>
     */
    public static function validSignatureProvider(): iterable
    {
        $rawBody = '{"id":"evt_valid","type":"payment_intent.succeeded"}';
        $timestamp = '1700000000';
        $signature = self::signature($timestamp, $rawBody);

        yield 'standard Stripe-Signature header' => [
            $rawBody,
            [
                'Stripe-Signature' => 't=' . $timestamp . ',v1=' . $signature,
            ],
        ];

        yield 'case-insensitive header name' => [
            $rawBody,
            [
                'stripe-signature' => 't=' . $timestamp . ',v1=' . $signature,
            ],
        ];

        yield 'multiple v1 signatures with one valid value' => [
            $rawBody,
            [
                'Stripe-Signature' => 't=' . $timestamp . ',v1=invalid_signature,v1=' . $signature,
            ],
        ];

        $pastBoundaryTimestamp = '1699999700';

        yield 'timestamp at past tolerance boundary' => [
            $rawBody,
            [
                'Stripe-Signature' => 't=' . $pastBoundaryTimestamp . ',v1=' . self::signature($pastBoundaryTimestamp, $rawBody),
            ],
        ];

        $futureBoundaryTimestamp = '1700000300';

        yield 'timestamp at future tolerance boundary' => [
            $rawBody,
            [
                'Stripe-Signature' => 't=' . $futureBoundaryTimestamp . ',v1=' . self::signature($futureBoundaryTimestamp, $rawBody),
            ],
        ];
    }

    /**
     * @dataProvider invalidSignatureProvider
     */
    public function testRejectsInvalidSignatureCases(
        string $rawBody,
        array $headers,
        string $expectedReasonCode,
        string $expectedReasonMessage,
    ): void {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: $headers,
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame($expectedReasonCode, $result->reason->code->value);
        $this->assertSame($expectedReasonMessage, $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{string, array<string, string|list<string>>, string, string}>
     */
    public static function invalidSignatureProvider(): iterable
    {
        $rawBody = '{"id":"evt_invalid","type":"payment_intent.succeeded"}';
        $timestamp = '1700000000';

        yield 'missing Stripe-Signature header' => [
            $rawBody,
            [],
            'stripe_signature_header_missing',
            'Stripe-Signature header is missing.',
        ];

        yield 'malformed Stripe-Signature header part' => [
            $rawBody,
            [
                'Stripe-Signature' => 't=' . $timestamp . ',broken_part,v1=' . self::signature($timestamp, $rawBody),
            ],
            'stripe_signature_header_malformed',
            'Stripe-Signature header is malformed.',
        ];

        yield 'missing timestamp' => [
            $rawBody,
            [
                'Stripe-Signature' => 'v1=' . self::signature($timestamp, $rawBody),
            ],
            'stripe_signature_timestamp_missing',
            'Stripe-Signature header does not contain a timestamp.',
        ];

        yield 'invalid timestamp' => [
            $rawBody,
            [
                'Stripe-Signature' => 't=not-a-timestamp,v1=invalid_signature',
            ],
            'stripe_signature_timestamp_invalid',
            'Stripe-Signature header timestamp must be an integer.',
        ];

        yield 'missing v1 signature' => [
            $rawBody,
            [
                'Stripe-Signature' => 't=' . $timestamp . ',v0=legacy_signature',
            ],
            'stripe_signature_missing',
            'Stripe-Signature header does not contain a v1 signature.',
        ];

        yield 'signature mismatch' => [
            $rawBody,
            [
                'Stripe-Signature' => 't=' . $timestamp . ',v1=invalid_signature',
            ],
            'stripe_signature_mismatch',
            'Stripe webhook signature does not match the request payload.',
        ];

        $tooOldTimestamp = '1699999699';

        yield 'expired timestamp with otherwise valid signature' => [
            $rawBody,
            [
                'Stripe-Signature' => 't=' . $tooOldTimestamp . ',v1=' . self::signature($tooOldTimestamp, $rawBody),
            ],
            'stripe_signature_timestamp_out_of_tolerance',
            'Stripe-Signature header timestamp is outside the allowed tolerance.',
        ];

        $tooFarInFutureTimestamp = '1700000301';

        yield 'future timestamp outside tolerance with otherwise valid signature' => [
            $rawBody,
            [
                'Stripe-Signature' => 't=' . $tooFarInFutureTimestamp . ',v1=' . self::signature($tooFarInFutureTimestamp, $rawBody),
            ],
            'stripe_signature_timestamp_out_of_tolerance',
            'Stripe-Signature header timestamp is outside the allowed tolerance.',
        ];
    }

    private function validator(): WebhookStripeValidator
    {
        return new WebhookStripeValidator(
            signingSecret: 'whsec_test_secret',
            timestampToleranceSeconds: 300,
            currentTimestamp: 1700000000,
        );
    }

    private static function signature(string $timestamp, string $rawBody): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, 'whsec_test_secret');
    }
}
