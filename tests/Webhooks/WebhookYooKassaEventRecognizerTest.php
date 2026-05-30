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

    #[DataProvider('basicPaymentEventTypesProvider')]
    public function testRecognizesProviderAndNormalizedEventTypeFromYooKassaPayload(
        string $providerEventType,
        WebhookEventType $expectedEventType,
    ): void {
        $recognizer = new WebhookYooKassaEventRecognizer();
        $input = new WebhookInput(rawBody: json_encode(['event' => $providerEventType], JSON_THROW_ON_ERROR));

        $recognizedProviderEventType = $recognizer->recognizeProviderEventType($input);

        $this->assertSame($providerEventType, $recognizedProviderEventType);
        $this->assertSame($expectedEventType, $recognizer->recognizeEventType($recognizedProviderEventType));
    }

    #[DataProvider('invalidYooKassaPayloadProvider')]
    public function testReturnsNullForInvalidYooKassaPayloadWithoutException(string $rawBody): void
    {
        $recognizer = new WebhookYooKassaEventRecognizer();

        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: $rawBody)));
    }

    public function testReturnsNullForUnknownProviderEventType(): void
    {
        $recognizer = new WebhookYooKassaEventRecognizer();

        $this->assertNull($recognizer->recognizeEventType('payment.expired'));
        $this->assertNull($recognizer->recognizeEventType('payout.succeeded'));
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidYooKassaPayloadProvider(): iterable
    {
        yield 'malformed json' => ['{"event":"payment.succeeded"'];
        yield 'json list' => ['[]'];
        yield 'json null' => ['null'];
        yield 'missing event' => ['{"type":"notification"}'];
        yield 'null event' => ['{"event":null}'];
        yield 'object event' => ['{"event":{"name":"payment.succeeded"}}'];
    }
}
