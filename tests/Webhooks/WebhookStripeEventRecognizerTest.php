<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventRecognizerInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookStripeEventRecognizer;

final class WebhookStripeEventRecognizerTest extends TestCase
{
    public function testImplementsEventRecognizerContract(): void
    {
        $recognizer = new WebhookStripeEventRecognizer();

        $this->assertInstanceOf(WebhookEventRecognizerInterface::class, $recognizer);
    }

    public function testRecognizesProviderEventTypeFromStripeJsonPayload(): void
    {
        $recognizer = new WebhookStripeEventRecognizer();
        $input = new WebhookInput(rawBody: '{"id":"evt_123","type":"payment_intent.succeeded"}');

        $this->assertSame('payment_intent.succeeded', $recognizer->recognizeProviderEventType($input));
    }

    public function testReturnsNullWhenProviderEventTypeCannotBeReadFromStripeJsonPayload(): void
    {
        $recognizer = new WebhookStripeEventRecognizer();

        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '')));
        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '{}')));
        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '{"type":123}')));
    }

    #[DataProvider('basicPaymentEventTypesProvider')]
    public function testRecognizesBasicPaymentRelatedEventTypes(
        string $providerEventType,
        WebhookEventType $expectedEventType,
    ): void {
        $recognizer = new WebhookStripeEventRecognizer();

        $this->assertSame($expectedEventType, $recognizer->recognizeEventType($providerEventType));
    }

    #[DataProvider('basicPaymentEventTypesProvider')]
    public function testRecognizesProviderAndNormalizedEventTypeFromStripePayload(
        string $providerEventType,
        WebhookEventType $expectedEventType,
    ): void {
        $recognizer = new WebhookStripeEventRecognizer();
        $input = new WebhookInput(rawBody: json_encode(['type' => $providerEventType], JSON_THROW_ON_ERROR));

        $recognizedProviderEventType = $recognizer->recognizeProviderEventType($input);

        $this->assertSame($providerEventType, $recognizedProviderEventType);
        $this->assertSame($expectedEventType, $recognizer->recognizeEventType($recognizedProviderEventType));
    }

    #[DataProvider('invalidStripePayloadProvider')]
    public function testReturnsNullForInvalidStripePayloadWithoutException(string $rawBody): void
    {
        $recognizer = new WebhookStripeEventRecognizer();

        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: $rawBody)));
    }

    public function testReturnsNullForUnknownProviderEventType(): void
    {
        $recognizer = new WebhookStripeEventRecognizer();

        $this->assertNull($recognizer->recognizeEventType('customer.created'));
        $this->assertNull($recognizer->recognizeEventType('payment_intent.partially_refunded'));
    }

    public function testHandlesUnknownStripeEventTypeWithoutException(): void
    {
        $recognizer = new WebhookStripeEventRecognizer();
        $input = new WebhookInput(rawBody: '{"id":"evt_123","type":"customer.created"}');

        $providerEventType = $recognizer->recognizeProviderEventType($input);

        $this->assertSame('customer.created', $providerEventType);
        $this->assertNull($recognizer->recognizeEventType($providerEventType));
    }

    /**
     * @return iterable<string, array{string, WebhookEventType}>
     */
    public static function basicPaymentEventTypesProvider(): iterable
    {
        yield 'created' => ['payment_intent.created', WebhookEventType::PaymentCreated];
        yield 'processing' => ['payment_intent.processing', WebhookEventType::PaymentProcessing];
        yield 'requires action' => ['payment_intent.requires_action', WebhookEventType::PaymentRequiresAction];
        yield 'requires capture' => [
            'payment_intent.amount_capturable_updated',
            WebhookEventType::PaymentRequiresCapture,
        ];
        yield 'succeeded' => ['payment_intent.succeeded', WebhookEventType::PaymentSucceeded];
        yield 'failed' => ['payment_intent.payment_failed', WebhookEventType::PaymentFailed];
        yield 'canceled' => ['payment_intent.canceled', WebhookEventType::PaymentCanceled];
        yield 'refunded' => ['charge.refunded', WebhookEventType::PaymentRefunded];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidStripePayloadProvider(): iterable
    {
        yield 'malformed json' => ['{"type":"payment_intent.succeeded"'];
        yield 'json list' => ['[]'];
        yield 'json null' => ['null'];
        yield 'missing type' => ['{"id":"evt_123"}'];
        yield 'null type' => ['{"type":null}'];
        yield 'object type' => ['{"type":{"name":"payment_intent.succeeded"}}'];
    }
}
