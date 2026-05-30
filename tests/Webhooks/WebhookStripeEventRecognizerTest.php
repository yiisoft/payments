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

    public function testReturnsNullForUnknownProviderEventType(): void
    {
        $recognizer = new WebhookStripeEventRecognizer();

        $this->assertNull($recognizer->recognizeEventType('customer.created'));
        $this->assertNull($recognizer->recognizeEventType('payment_intent.partially_refunded'));
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
}
