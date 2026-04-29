<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookYooKassaValidator;

final class WebhookYooKassaValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = new WebhookYooKassaValidator();

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('yookassa', $validator->getProviderId());
    }

    public function testReturnsFailClosedWhenAuthenticityIndicatorsAreNotAvailable(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: [],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_authenticity_indicators_not_available', $result->reason->code->value);
        $this->assertSame(
            'YooKassa webhook signature-level validation is not supported in R1 because the current API/config does not expose a webhook-specific authenticity indicator.',
            $result->reason->message,
        );
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    public function testDoesNotReturnSuccessWhenSignatureLevelValidationIsUnavailableInR1(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: [
                'Content-Type' => ['application/json'],
                'User-Agent' => ['YooKassa'],
            ],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_authenticity_indicators_not_available', $result->reason->code->value);
        $this->assertStringContainsString('signature-level validation is not supported in R1', $result->reason->message);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    public function testCurrentApiAndConfigDoNotExposeWebhookSpecificAuthenticityIndicator(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: [
                'Content-Type' => ['application/json'],
                'User-Agent' => ['YooKassa'],
            ],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_authenticity_indicators_not_available', $result->reason->code->value);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    public function testAcceptsValidPaymentSucceededRequestStructureBeforeR1AuthenticityLimitation(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: json_encode([
                'type' => 'notification',
                'event' => 'payment.succeeded',
                'object' => [
                    'id' => 'payment-id',
                    'status' => 'succeeded',
                    'paid' => true,
                ],
            ], JSON_THROW_ON_ERROR),
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_authenticity_indicators_not_available', $result->reason->code->value);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    public function testAcceptsValidRefundSucceededRequestStructureBeforeR1AuthenticityLimitation(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: json_encode([
                'type' => 'notification',
                'event' => 'refund.succeeded',
                'object' => [
                    'id' => 'refund-id',
                    'status' => 'succeeded',
                    'payment_id' => 'payment-id',
                ],
            ], JSON_THROW_ON_ERROR),
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_authenticity_indicators_not_available', $result->reason->code->value);
        $this->assertSame('refund.succeeded', $result->reason->providerEventType);
    }

    public function testAcceptsValidRequestStructureWithAdditionalPayloadFieldsBeforeR1AuthenticityLimitation(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: json_encode([
                'type' => 'notification',
                'event' => 'payment.canceled',
                'object' => [
                    'id' => 'payment-id',
                    'status' => 'canceled',
                    'metadata' => [
                        'order_id' => 'order-123',
                    ],
                ],
                'extra_provider_field' => 'extra-value',
            ], JSON_THROW_ON_ERROR),
            headers: [
                'Content-Type' => ['application/json'],
                'User-Agent' => ['YooKassa'],
            ],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_authenticity_indicators_not_available', $result->reason->code->value);
        $this->assertSame('payment.canceled', $result->reason->providerEventType);
    }

    public function testRejectsEmptyPayload(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '   ',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_payload_empty', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsMalformedJsonPayload(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded",',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_payload_malformed_json', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsNonObjectJsonPayload(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '"payment.succeeded"',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_payload_invalid', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsPayloadWithoutEvent(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"object":{"id":"payment-id"}}',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_event_missing', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsPayloadWithEmptyEvent(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"   ","object":{"id":"payment-id"}}',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_event_missing', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsPayloadWithoutObject(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded"}',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_object_missing', $result->reason->code->value);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    public function testRejectsPayloadWithNonObjectObjectField(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":"payment-id"}',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_object_missing', $result->reason->code->value);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    #[DataProvider('invalidEventPayloadProvider')]
    public function testRejectsPayloadWithInvalidEventField(string $rawBody): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_event_missing', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEventPayloadProvider(): iterable
    {
        yield 'event is null' => ['{"event":null,"object":{"id":"payment-id"}}'];
        yield 'event is boolean' => ['{"event":true,"object":{"id":"payment-id"}}'];
        yield 'event is number' => ['{"event":123,"object":{"id":"payment-id"}}'];
        yield 'event is array' => ['{"event":["payment.succeeded"],"object":{"id":"payment-id"}}'];
    }

    #[DataProvider('invalidObjectPayloadProvider')]
    public function testRejectsPayloadWithAdditionalInvalidObjectFields(string $rawBody): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_object_missing', $result->reason->code->value);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidObjectPayloadProvider(): iterable
    {
        yield 'object is null' => ['{"event":"payment.succeeded","object":null}'];
        yield 'object is boolean' => ['{"event":"payment.succeeded","object":false}'];
        yield 'object is number' => ['{"event":"payment.succeeded","object":123}'];
    }
}
