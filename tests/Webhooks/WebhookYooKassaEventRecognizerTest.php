<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventRecognizerInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookYooKassaEventRecognizer;

final class WebhookYooKassaEventRecognizerTest extends TestCase
{
    public function testImplementsEventRecognizerContract(): void
    {
        $recognizer = new WebhookYooKassaEventRecognizer();

        $this->assertInstanceOf(WebhookEventRecognizerInterface::class, $recognizer);
    }

    public function testRecognizesProviderEventTypeFromYooKassaJsonPayload(): void
    {
        $recognizer = new WebhookYooKassaEventRecognizer();
        $input = new WebhookInput(rawBody: '{"type":"notification","event":"payment.succeeded"}');

        $this->assertSame('payment.succeeded', $recognizer->recognizeProviderEventType($input));
    }

    public function testReturnsNullWhenProviderEventTypeCannotBeReadFromYooKassaJsonPayload(): void
    {
        $recognizer = new WebhookYooKassaEventRecognizer();

        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '')));
        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '{}')));
        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '{"event":123}')));
    }

    public function testUnknownProviderEventTypeDoesNotFail(): void
    {
        $recognizer = new WebhookYooKassaEventRecognizer();
        $input = new WebhookInput(rawBody: '{"type":"notification","event":"payment.expired"}');

        $providerEventType = $recognizer->recognizeProviderEventType($input);

        $this->assertSame('payment.expired', $providerEventType);
        $this->assertNull($recognizer->recognizeEventType($providerEventType));
    }

    #[DataProvider('basicPaymentEventTypesProvider')]
    public function testRecognizesBasicPaymentRelatedEventTypes(
        string $providerEventType,
        WebhookEventType $expectedEventType,
    ): void {
        $recognizer = new WebhookYooKassaEventRecognizer();

        $this->assertSame($expectedEventType, $recognizer->recognizeEventType($providerEventType));
    }

    /**
     * @return iterable<string, array{string, WebhookEventType}>
     */
    public static function basicPaymentEventTypesProvider(): iterable
    {
        yield 'waiting for capture' => ['payment.waiting_for_capture', WebhookEventType::PaymentRequiresCapture];
        yield 'succeeded' => ['payment.succeeded', WebhookEventType::PaymentSucceeded];
        yield 'canceled' => ['payment.canceled', WebhookEventType::PaymentCanceled];
        yield 'refunded' => ['refund.succeeded', WebhookEventType::PaymentRefunded];
    }
}
