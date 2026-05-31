<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventRecognizerInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalEventRecognizer;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookPaymentOutcomeRules;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookStripeEventRecognizer;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookYooKassaEventRecognizer;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentWebhookMapper;

final class CrossProviderRefundNormalizationBoundaryTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('refundLikeProviderEventProvider')]
    public function testRefundLikeEventsStayOutsideR1RefundNormalizationAcrossProviders(
        string $providerId,
        WebhookEventRecognizerInterface $recognizer,
        WebhookPaymentMapperInterface $mapper,
        string $rawBody,
        string $providerEventType,
        array $data,
        ?string $paymentStatus,
    ): void {
        $recognizedProviderEventType = $recognizer->recognizeProviderEventType(new WebhookInput(rawBody: $rawBody));

        $this->assertSame($providerEventType, $recognizedProviderEventType);
        $this->assertSame(WebhookEventType::PaymentRefunded, $recognizer->recognizeEventType($providerEventType));
        $this->assertFalse(WebhookPaymentOutcomeRules::shouldProcess(WebhookEventType::PaymentRefunded));
        $this->assertTrue(WebhookPaymentOutcomeRules::shouldRejectAsUnsupported(WebhookEventType::PaymentRefunded));

        $rawData = new WebhookRawData(
            rawBody: $rawBody,
            headers: [],
            payload: $data,
            providerEventType: $providerEventType,
        );
        $payload = new WebhookPayload(
            providerId: $providerId,
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: $providerEventType,
            data: $data,
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

    public function testRobokassaDoesNotDeclareRefundLikeResultUrlOutcomeInR1(): void
    {
        $this->assertFalse(WebhookRobokassaCallbackFormat::supportsR1PaymentOutcome(WebhookEventType::PaymentRefunded));
        $this->assertTrue(WebhookPaymentOutcomeRules::shouldRejectAsUnsupported(WebhookEventType::PaymentRefunded));
    }

    /**
     * @return iterable<string, array{
     *     string,
     *     WebhookEventRecognizerInterface,
     *     WebhookPaymentMapperInterface,
     *     string,
     *     string,
     *     array<string, mixed>,
     *     string
     * }>
     */
    public static function refundLikeProviderEventProvider(): iterable
    {
        yield 'stripe charge.refunded' => [
            'stripe',
            new WebhookStripeEventRecognizer(),
            new WebhookStripePaymentWebhookMapper(),
            '{"id":"evt_refunded","type":"charge.refunded","data":{"object":{"status":"succeeded"}}}',
            'charge.refunded',
            ['type' => 'charge.refunded', 'data' => ['object' => ['status' => 'succeeded']]],
            'succeeded',
        ];

        yield 'paypal capture refunded' => [
            'paypal',
            new WebhookPayPalEventRecognizer(),
            new WebhookPayPalPaymentWebhookMapper(),
            '{"id":"WH-REFUNDED","event_type":"PAYMENT.CAPTURE.REFUNDED","resource":{"status":"REFUNDED"}}',
            'PAYMENT.CAPTURE.REFUNDED',
            ['event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => ['status' => 'REFUNDED']],
            'REFUNDED',
        ];

        yield 'paypal capture reversed' => [
            'paypal',
            new WebhookPayPalEventRecognizer(),
            new WebhookPayPalPaymentWebhookMapper(),
            '{"id":"WH-REVERSED","event_type":"PAYMENT.CAPTURE.REVERSED","resource":{"status":"REVERSED"}}',
            'PAYMENT.CAPTURE.REVERSED',
            ['event_type' => 'PAYMENT.CAPTURE.REVERSED', 'resource' => ['status' => 'REVERSED']],
            'REVERSED',
        ];

        yield 'yookassa refund.succeeded' => [
            'yookassa',
            new WebhookYooKassaEventRecognizer(),
            new WebhookYooKassaPaymentWebhookMapper(),
            '{"type":"notification","event":"refund.succeeded","object":{"status":"succeeded"}}',
            'refund.succeeded',
            ['type' => 'notification', 'event' => 'refund.succeeded', 'object' => ['status' => 'succeeded']],
            'succeeded',
        ];
    }
}
