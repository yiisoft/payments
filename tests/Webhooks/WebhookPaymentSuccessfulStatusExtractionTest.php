<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentMapper;

final class WebhookPaymentSuccessfulStatusExtractionTest extends TestCase
{
    /**
     * @return iterable<string, array{WebhookPaymentMapperInterface, WebhookPayload, string}>
     */
    public static function successfulPaymentStatusProvider(): iterable
    {
        yield 'stripe succeeded payment intent' => [
            new WebhookStripePaymentMapper(),
            new WebhookPayload(
                providerId: 'stripe',
                eventType: WebhookEventType::PaymentSucceeded,
                providerEventType: 'payment_intent.succeeded',
                data: ['data' => ['object' => ['status' => 'succeeded']]],
            ),
            'succeeded',
        ];

        yield 'paypal completed checkout order' => [
            new WebhookPayPalPaymentMapper(),
            new WebhookPayload(
                providerId: 'paypal',
                eventType: WebhookEventType::PaymentSucceeded,
                providerEventType: 'CHECKOUT.ORDER.APPROVED',
                data: ['resource' => ['status' => 'COMPLETED']],
            ),
            'COMPLETED',
        ];

        yield 'yookassa succeeded payment object' => [
            new WebhookYooKassaPaymentMapper(),
            new WebhookPayload(
                providerId: 'yookassa',
                eventType: WebhookEventType::PaymentSucceeded,
                providerEventType: 'payment.succeeded',
                data: ['object' => ['status' => 'succeeded']],
            ),
            'succeeded',
        ];

        yield 'robokassa result url callback' => [
            new WebhookRobokassaPaymentMapper(),
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
