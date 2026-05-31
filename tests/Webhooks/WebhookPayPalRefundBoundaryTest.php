<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalEventRecognizer;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookPaymentOutcomeRules;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookPayPalRefundBoundaryTest extends TestCase
{
    #[DataProvider('refundLikePayPalCaptureEventProvider')]
    public function testRecognizesRefundedAndReversedCaptureAsRefundLikeEventsOutsideR1PaymentNormalization(
        string $providerEventType,
        string $paymentStatus,
    ): void {
        $recognizer = new WebhookPayPalEventRecognizer();
        $input = new WebhookInput(
            rawBody: sprintf(
                '{"id":"WH-123","event_type":"%s","resource":{"status":"%s"}}',
                $providerEventType,
                $paymentStatus,
            ),
        );

        $recognizedProviderEventType = $recognizer->recognizeProviderEventType($input);

        $this->assertSame($providerEventType, $recognizedProviderEventType);
        $this->assertSame(WebhookEventType::PaymentRefunded, $recognizer->recognizeEventType($recognizedProviderEventType));
        $this->assertFalse(WebhookPaymentOutcomeRules::shouldProcess(WebhookEventType::PaymentRefunded));
        $this->assertTrue(WebhookPaymentOutcomeRules::shouldRejectAsUnsupported(WebhookEventType::PaymentRefunded));
    }

    #[DataProvider('refundLikePayPalCaptureEventProvider')]
    public function testKeepsRefundedAndReversedCaptureUnsupportedInR1PayPalMapper(
        string $providerEventType,
        string $paymentStatus,
    ): void {
        $mapper = new WebhookPayPalPaymentMapper();
        $rawData = new WebhookRawData(
            rawBody: sprintf('{"event_type":"%s","resource":{"status":"%s"}}', $providerEventType, $paymentStatus),
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            payload: ['event_type' => $providerEventType, 'resource' => ['status' => $paymentStatus]],
            providerEventType: $providerEventType,
        );
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: $providerEventType,
            data: ['event_type' => $providerEventType, 'resource' => ['status' => $paymentStatus]],
            paymentStatus: $paymentStatus,
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame($providerEventType, $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
        $this->assertNull($result->paymentStatus);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refundLikePayPalCaptureEventProvider(): iterable
    {
        yield 'refunded capture' => ['PAYMENT.CAPTURE.REFUNDED', 'REFUNDED'];
        yield 'reversed capture' => ['PAYMENT.CAPTURE.REVERSED', 'REVERSED'];
    }
}
