<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentWebhookMapper;

final class PaymentWebhookSuccessfulStatusExtractionTest extends TestCase
{
    /**
     * @return iterable<string, array{WebhookPaymentMapperInterface, WebhookPayload, string}>
     */
    public static function successfulPaymentStatusProvider(): iterable
    {
        yield 'stripe succeeded payment intent' => [
            new WebhookStripePaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'stripe',
                eventType: WebhookEventType::PaymentSucceeded,
                providerEventType: 'payment_intent.succeeded',
                data: ['data' => ['object' => ['status' => 'succeeded']]],
            ),
            'succeeded',
        ];

        yield 'paypal completed checkout order' => [
            new WebhookPayPalPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'paypal',
                eventType: WebhookEventType::PaymentSucceeded,
                providerEventType: 'CHECKOUT.ORDER.APPROVED',
                data: ['resource' => ['status' => 'COMPLETED']],
            ),
            'COMPLETED',
        ];

        yield 'yookassa succeeded payment object' => [
            new WebhookYooKassaPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: 'yookassa',
                eventType: WebhookEventType::PaymentSucceeded,
                providerEventType: 'payment.succeeded',
                data: ['object' => ['status' => 'succeeded']],
            ),
            'succeeded',
        ];

        yield 'robokassa result url callback' => [
            new WebhookRobokassaPaymentWebhookMapper(),
            new WebhookPayload(
                providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
                eventType: WebhookEventType::PaymentSucceeded,
                providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            ),
            WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
        ];
    }

    #[DataProvider('successfulPaymentStatusProvider')]
    public function testExtractsSuccessfulPaidLikeStatusForAllProviders(
        WebhookPaymentMapperInterface $mapper,
        WebhookPayload $payload,
        string $expectedStatus,
    ): void {
        $this->assertSame($expectedStatus, $mapper->extractPaymentStatus($payload));
    }
}
