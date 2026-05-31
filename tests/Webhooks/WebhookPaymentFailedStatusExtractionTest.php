<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentMapper;

final class WebhookPaymentFailedStatusExtractionTest extends TestCase
{
    /**
     * @return iterable<string, array{WebhookPaymentMapperInterface, WebhookPayload, string}>
     */
    public static function failedCanceledPaymentStatusProvider(): iterable
    {
        yield 'stripe failed payment intent' => [
            new WebhookStripePaymentMapper(),
            new WebhookPayload(
                providerId: 'stripe',
                eventType: WebhookEventType::PaymentFailed,
                providerEventType: 'payment_intent.payment_failed',
                data: ['data' => ['object' => ['status' => 'requires_payment_method']]],
            ),
            'requires_payment_method',
        ];

        yield 'stripe canceled payment intent' => [
            new WebhookStripePaymentMapper(),
            new WebhookPayload(
                providerId: 'stripe',
                eventType: WebhookEventType::PaymentCanceled,
                providerEventType: 'payment_intent.canceled',
                data: ['data' => ['object' => ['status' => 'canceled']]],
            ),
            'canceled',
        ];

        yield 'paypal denied capture' => [
            new WebhookPayPalPaymentMapper(),
            new WebhookPayload(
                providerId: 'paypal',
                eventType: WebhookEventType::PaymentFailed,
                providerEventType: 'PAYMENT.CAPTURE.DENIED',
                data: ['resource' => ['status' => 'DENIED']],
            ),
            'DENIED',
        ];

        yield 'paypal declined capture' => [
            new WebhookPayPalPaymentMapper(),
            new WebhookPayload(
                providerId: 'paypal',
                eventType: WebhookEventType::PaymentFailed,
                providerEventType: 'PAYMENT.CAPTURE.DECLINED',
                data: ['resource' => ['status' => 'DECLINED']],
            ),
            'DECLINED',
        ];

        yield 'paypal reversed payment approval' => [
            new WebhookPayPalPaymentMapper(),
            new WebhookPayload(
                providerId: 'paypal',
                eventType: WebhookEventType::PaymentCanceled,
                providerEventType: 'CHECKOUT.PAYMENT-APPROVAL.REVERSED',
                data: ['resource' => ['status' => 'REVERSED']],
            ),
            'REVERSED',
        ];

        yield 'yookassa canceled payment' => [
            new WebhookYooKassaPaymentMapper(),
            new WebhookPayload(
                providerId: 'yookassa',
                eventType: WebhookEventType::PaymentCanceled,
                providerEventType: 'payment.canceled',
                data: ['object' => ['status' => 'canceled']],
            ),
            'canceled',
        ];
    }

    #[DataProvider('failedCanceledPaymentStatusProvider')]
    public function testExtractsFailedCanceledLikeStatusWhereProviderExposesIt(
        WebhookPaymentMapperInterface $mapper,
        WebhookPayload $payload,
        string $expectedStatus,
    ): void {
        $this->assertSame($expectedStatus, $mapper->extractPaymentStatus($payload));
    }
}
