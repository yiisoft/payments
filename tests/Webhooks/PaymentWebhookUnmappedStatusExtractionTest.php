<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentWebhookMapper;

final class PaymentWebhookUnmappedStatusExtractionTest extends TestCase
{
    /**
     * @return iterable<string, array{WebhookPaymentMapperInterface, WebhookPayload, string}>
     */
    public static function providerStringStatusProvider(): iterable
    {
        yield 'stripe future provider status' => [
            new WebhookStripePaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'stripe',
                eventType: WebhookEventType::PaymentProcessing,
                providerEventType: 'payment_intent.processing',
                data: ['data' => ['object' => ['status' => 'provider_future_status']]],
            ),
            'provider_future_status',
        ];

        yield 'paypal future provider status' => [
            new WebhookPayPalPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'paypal',
                eventType: WebhookEventType::PaymentProcessing,
                providerEventType: 'PAYMENT.CAPTURE.PENDING',
                data: ['resource' => ['status' => 'PROVIDER_FUTURE_STATUS']],
            ),
            'PROVIDER_FUTURE_STATUS',
        ];

        yield 'yookassa future provider status' => [
            new WebhookYooKassaPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'yookassa',
                eventType: WebhookEventType::PaymentProcessing,
                providerEventType: 'payment.waiting_for_capture',
                data: ['object' => ['status' => 'provider_future_status']],
            ),
            'provider_future_status',
        ];
    }

    #[DataProvider('providerStringStatusProvider')]
    public function testReturnsProviderStringStatusWithoutNormalizingOrThrowing(
        WebhookPaymentMapperInterface $mapper,
        WebhookPayload $payload,
        string $expectedStatus,
    ): void {
        $this->assertSame($expectedStatus, $mapper->extractPaymentStatus($payload));
    }

    /**
     * @return iterable<string, array{WebhookPaymentMapperInterface, WebhookPayload}>
     */
    public static function unmappedStatusPayloadProvider(): iterable
    {
        yield 'stripe status field has unsupported shape' => [
            new WebhookStripePaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'stripe',
                eventType: WebhookEventType::PaymentProcessing,
                providerEventType: 'payment_intent.processing',
                data: ['data' => ['object' => ['status' => ['processing']]]],
            ),
        ];

        yield 'paypal status field has unsupported shape' => [
            new WebhookPayPalPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'paypal',
                eventType: WebhookEventType::PaymentProcessing,
                providerEventType: 'PAYMENT.CAPTURE.PENDING',
                data: ['resource' => ['status' => ['PENDING']]],
            ),
        ];

        yield 'yookassa status field has unsupported shape' => [
            new WebhookYooKassaPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'yookassa',
                eventType: WebhookEventType::PaymentProcessing,
                providerEventType: 'payment.waiting_for_capture',
                data: ['object' => ['status' => ['waiting_for_capture']]],
            ),
        ];

        yield 'robokassa unsupported callback status signal' => [
            new WebhookRobokassaPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'robokassa',
                eventType: WebhookEventType::PaymentSucceeded,
                providerEventType: 'unsupported_callback',
            ),
        ];

        yield 'robokassa ambiguous callback status signal' => [
            new WebhookRobokassaPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'robokassa',
                eventType: null,
                providerEventType: 'ambiguous_callback',
            ),
        ];
    }

    #[DataProvider('unmappedStatusPayloadProvider')]
    public function testReturnsNullForUnmappedProviderStatusWithoutThrowing(
        WebhookPaymentMapperInterface $mapper,
        WebhookPayload $payload,
    ): void {
        $this->assertNull($mapper->extractPaymentStatus($payload));
    }
}
