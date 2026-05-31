<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentWebhookMapper;

final class PaymentWebhookPendingStatusExtractionTest extends TestCase
{
    /**
     * @return iterable<string, array{WebhookPaymentMapperInterface, WebhookPayload, string}>
     */
    public static function pendingAuthorizedPaymentStatusProvider(): iterable
    {
        yield 'stripe processing payment intent' => [
            new WebhookStripePaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'stripe',
                eventType: WebhookEventType::PaymentProcessing,
                providerEventType: 'payment_intent.processing',
                data: ['data' => ['object' => ['status' => 'processing']]],
            ),
            'processing',
        ];

        yield 'stripe requires capture payment intent' => [
            new WebhookStripePaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'stripe',
                eventType: WebhookEventType::PaymentRequiresCapture,
                providerEventType: 'payment_intent.amount_capturable_updated',
                data: ['data' => ['object' => ['status' => 'requires_capture']]],
            ),
            'requires_capture',
        ];

        yield 'paypal pending capture' => [
            new WebhookPayPalPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'paypal',
                eventType: WebhookEventType::PaymentProcessing,
                providerEventType: 'PAYMENT.CAPTURE.PENDING',
                data: ['resource' => ['status' => 'PENDING']],
            ),
            'PENDING',
        ];

        yield 'paypal created authorization' => [
            new WebhookPayPalPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'paypal',
                eventType: WebhookEventType::PaymentRequiresCapture,
                providerEventType: 'PAYMENT.AUTHORIZATION.CREATED',
                data: ['resource' => ['status' => 'CREATED']],
            ),
            'CREATED',
        ];

        yield 'yookassa waiting for capture payment' => [
            new WebhookYooKassaPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'yookassa',
                eventType: WebhookEventType::PaymentRequiresCapture,
                providerEventType: 'payment.waiting_for_capture',
                data: ['object' => ['status' => 'waiting_for_capture']],
            ),
            'waiting_for_capture',
        ];
    }

    #[DataProvider('pendingAuthorizedPaymentStatusProvider')]
    public function testExtractsPendingAuthorizedLikeStatusWhereProviderExposesIt(
        WebhookPaymentMapperInterface $mapper,
        WebhookPayload $payload,
        string $expectedStatus,
    ): void {
        $this->assertSame($expectedStatus, $mapper->extractPaymentStatus($payload));
    }
}
