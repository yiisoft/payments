<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventRecognizerInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalEventRecognizer;

final class WebhookPayPalEventRecognizerTest extends TestCase
{
    public function testImplementsEventRecognizerContract(): void
    {
        $recognizer = new WebhookPayPalEventRecognizer();

        $this->assertInstanceOf(WebhookEventRecognizerInterface::class, $recognizer);
    }

    public function testRecognizesProviderEventTypeFromPayPalJsonPayload(): void
    {
        $recognizer = new WebhookPayPalEventRecognizer();
        $input = new WebhookInput(rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}');

        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $recognizer->recognizeProviderEventType($input));
    }

    public function testReturnsNullWhenProviderEventTypeCannotBeReadFromPayPalJsonPayload(): void
    {
        $recognizer = new WebhookPayPalEventRecognizer();

        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '')));
        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '{}')));
        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '{"event_type":123}')));
    }

    #[DataProvider('basicPaymentEventTypesProvider')]
    public function testRecognizesBasicPaymentRelatedEventTypes(
        string $providerEventType,
        WebhookEventType $expectedEventType,
    ): void {
        $recognizer = new WebhookPayPalEventRecognizer();

        $this->assertSame($expectedEventType, $recognizer->recognizeEventType($providerEventType));
    }

    /**
     * @return iterable<string, array{string, WebhookEventType}>
     */
    public static function basicPaymentEventTypesProvider(): iterable
    {
        yield 'approved order' => ['CHECKOUT.ORDER.APPROVED', WebhookEventType::PaymentRequiresCapture];
        yield 'approval reversed' => ['CHECKOUT.PAYMENT-APPROVAL.REVERSED', WebhookEventType::PaymentCanceled];
        yield 'authorization created' => ['PAYMENT.AUTHORIZATION.CREATED', WebhookEventType::PaymentRequiresCapture];
        yield 'capture pending' => ['PAYMENT.CAPTURE.PENDING', WebhookEventType::PaymentProcessing];
        yield 'capture completed' => ['PAYMENT.CAPTURE.COMPLETED', WebhookEventType::PaymentSucceeded];
        yield 'capture denied' => ['PAYMENT.CAPTURE.DENIED', WebhookEventType::PaymentFailed];
        yield 'capture declined' => ['PAYMENT.CAPTURE.DECLINED', WebhookEventType::PaymentFailed];
        yield 'capture refunded' => ['PAYMENT.CAPTURE.REFUNDED', WebhookEventType::PaymentRefunded];
        yield 'capture reversed' => ['PAYMENT.CAPTURE.REVERSED', WebhookEventType::PaymentRefunded];
    }
}
