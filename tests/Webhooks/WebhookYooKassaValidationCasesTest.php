<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookYooKassaValidator;

final class WebhookYooKassaValidationCasesTest extends TestCase
{
    #[DataProvider('validStructuralPayloadProvider')]
    public function testAcceptsValidStructuralCases(string $rawBody, string $expectedProviderEventType): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_authenticity_indicators_not_available', $result->reason->code->value);
        $this->assertSame(
            'YooKassa webhook signature-level validation is not supported in R1 because the current API/config does not expose a webhook-specific authenticity indicator.',
            $result->reason->message,
        );
        $this->assertSame($expectedProviderEventType, $result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validStructuralPayloadProvider(): iterable
    {
        yield 'payment succeeded notification' => [
            self::payload([
                'type' => 'notification',
                'event' => 'payment.succeeded',
                'object' => [
                    'id' => 'payment-id',
                    'status' => 'succeeded',
                    'paid' => true,
                ],
            ]),
            'payment.succeeded',
        ];

        yield 'payment canceled notification' => [
            self::payload([
                'type' => 'notification',
                'event' => 'payment.canceled',
                'object' => [
                    'id' => 'payment-id',
                    'status' => 'canceled',
                    'cancellation_details' => [
                        'party' => 'yoo_money',
                        'reason' => 'expired_on_capture',
                    ],
                ],
            ]),
            'payment.canceled',
        ];

        yield 'refund succeeded notification' => [
            self::payload([
                'type' => 'notification',
                'event' => 'refund.succeeded',
                'object' => [
                    'id' => 'refund-id',
                    'status' => 'succeeded',
                    'payment_id' => 'payment-id',
                ],
            ]),
            'refund.succeeded',
        ];

        yield 'notification with additional provider fields' => [
            self::payload([
                'type' => 'notification',
                'event' => 'payment.waiting_for_capture',
                'object' => [
                    'id' => 'payment-id',
                    'status' => 'waiting_for_capture',
                    'metadata' => [
                        'order_id' => 'order-123',
                    ],
                ],
                'extra_provider_field' => 'extra-value',
            ]),
            'payment.waiting_for_capture',
        ];
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testRejectsInvalidCases(
        string $rawBody,
        string $expectedReasonCode,
        string $expectedReasonMessage,
        ?string $expectedProviderEventType,
    ): void {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame($expectedReasonCode, $result->reason->code->value);
        $this->assertSame($expectedReasonMessage, $result->reason->message);
        $this->assertSame($expectedProviderEventType, $result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{string, string, string, string|null}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'empty payload' => [
            " \t\n ",
            'yookassa_payload_empty',
            'YooKassa webhook payload must be a non-empty JSON object.',
            null,
        ];

        yield 'malformed JSON payload' => [
            '{"event":"payment.succeeded",',
            'yookassa_payload_malformed_json',
            'YooKassa webhook payload must be valid JSON.',
            null,
        ];

        yield 'non-object JSON payload' => [
            'true',
            'yookassa_payload_invalid',
            'YooKassa webhook payload must be a JSON object.',
            null,
        ];

        yield 'missing event field' => [
            self::payload(['object' => ['id' => 'payment-id']]),
            'yookassa_event_missing',
            'YooKassa webhook payload must contain a non-empty event field.',
            null,
        ];

        yield 'empty event field' => [
            self::payload(['event' => '   ', 'object' => ['id' => 'payment-id']]),
            'yookassa_event_missing',
            'YooKassa webhook payload must contain a non-empty event field.',
            null,
        ];

        yield 'non-string event field' => [
            self::payload(['event' => ['payment.succeeded'], 'object' => ['id' => 'payment-id']]),
            'yookassa_event_missing',
            'YooKassa webhook payload must contain a non-empty event field.',
            null,
        ];

        yield 'missing object field' => [
            self::payload(['event' => 'payment.succeeded']),
            'yookassa_object_missing',
            'YooKassa webhook payload must contain an object field.',
            'payment.succeeded',
        ];

        yield 'non-object object field' => [
            self::payload(['event' => 'payment.succeeded', 'object' => 'payment-id']),
            'yookassa_object_missing',
            'YooKassa webhook payload must contain an object field.',
            'payment.succeeded',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function payload(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
