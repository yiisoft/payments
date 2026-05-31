<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentMapper;

final class WebhookPaymentMapperRefundUnsupportedTest extends TestCase
{
    #[DataProvider('refundLikePayloadProvider')]
    public function testRefundLikeEventsDoNotReturnProcessedInR1PaymentMappers(
        WebhookPaymentMapperInterface $mapper,
        WebhookPayload $payload,
    ): void {
        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame($payload->providerEventType, $result->reason->providerEventType);
        $this->assertSame($payload->rawData, $result->rawData);
        $this->assertNull($result->paymentStatus);
    }

    /**
     * @return iterable<string, array{WebhookPaymentMapperInterface, WebhookPayload}>
     */
    public static function refundLikePayloadProvider(): iterable
    {
        yield 'stripe charge refunded' => [
            new WebhookStripePaymentMapper(),
            self::payload(
                providerId: 'stripe',
                providerEventType: 'charge.refunded',
                rawBody: '{"type":"charge.refunded","data":{"object":{"status":"succeeded"}}}',
                data: ['type' => 'charge.refunded', 'data' => ['object' => ['status' => 'succeeded']]],
                paymentStatus: 'succeeded',
            ),
        ];

        yield 'paypal capture refunded' => [
            new WebhookPayPalPaymentMapper(),
            self::payload(
                providerId: 'paypal',
                providerEventType: 'PAYMENT.CAPTURE.REFUNDED',
                rawBody: '{"event_type":"PAYMENT.CAPTURE.REFUNDED","resource":{"status":"REFUNDED"}}',
                data: ['event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => ['status' => 'REFUNDED']],
                paymentStatus: 'REFUNDED',
            ),
        ];

        yield 'paypal capture reversed' => [
            new WebhookPayPalPaymentMapper(),
            self::payload(
                providerId: 'paypal',
                providerEventType: 'PAYMENT.CAPTURE.REVERSED',
                rawBody: '{"event_type":"PAYMENT.CAPTURE.REVERSED","resource":{"status":"REVERSED"}}',
                data: ['event_type' => 'PAYMENT.CAPTURE.REVERSED', 'resource' => ['status' => 'REVERSED']],
                paymentStatus: 'REVERSED',
            ),
        ];

        yield 'yookassa refund succeeded' => [
            new WebhookYooKassaPaymentMapper(),
            self::payload(
                providerId: 'yookassa',
                providerEventType: 'refund.succeeded',
                rawBody: '{"type":"notification","event":"refund.succeeded","object":{"status":"succeeded"}}',
                data: ['type' => 'notification', 'event' => 'refund.succeeded', 'object' => ['status' => 'succeeded']],
                paymentStatus: 'succeeded',
            ),
        ];

        yield 'robokassa synthetic refunded outcome' => [
            new WebhookRobokassaPaymentMapper(),
            self::payload(
                providerId: 'robokassa',
                providerEventType: 'payment.refunded',
                rawBody: 'OutSum=10.00&InvId=123',
                data: ['OutSum' => '10.00', 'InvId' => '123'],
                paymentStatus: null,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function payload(
        string $providerId,
        string $providerEventType,
        string $rawBody,
        array $data,
        ?string $paymentStatus,
    ): WebhookPayload {
        $rawData = new WebhookRawData(
            rawBody: $rawBody,
            headers: [],
            payload: $data,
            providerEventType: $providerEventType,
        );

        return new WebhookPayload(
            providerId: $providerId,
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: $providerEventType,
            data: $data,
            paymentStatus: $paymentStatus,
            rawData: $rawData,
        );
    }
}
