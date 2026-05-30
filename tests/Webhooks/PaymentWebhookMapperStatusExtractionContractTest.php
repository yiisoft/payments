<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentWebhookMapper;

final class PaymentWebhookMapperStatusExtractionContractTest extends TestCase
{
    /**
     * @return iterable<string, array{PaymentWebhookMapperInterface, string, string}>
     */
    public static function providerStatusMapperProvider(): iterable
    {
        yield 'stripe' => [new WebhookStripePaymentWebhookMapper(), 'stripe', 'succeeded'];
        yield 'paypal' => [new WebhookPayPalPaymentWebhookMapper(), 'paypal', 'COMPLETED'];
        yield 'yookassa' => [new WebhookYooKassaPaymentWebhookMapper(), 'yookassa', 'succeeded'];
    }

    #[DataProvider('providerStatusMapperProvider')]
    public function testMapperExtractsProviderStatusStringWhenItIsAvailableInPayload(
        PaymentWebhookMapperInterface $mapper,
        string $providerId,
        string $paymentStatus,
    ): void {
        $payload = new WebhookPayload(
            providerId: $providerId,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment.succeeded',
            paymentStatus: $paymentStatus,
        );

        $this->assertSame($paymentStatus, $mapper->extractPaymentStatus($payload));
    }

    /**
     * @return iterable<string, array{PaymentWebhookMapperInterface, string}>
     */
    public static function nullableStatusMapperProvider(): iterable
    {
        yield 'stripe' => [new WebhookStripePaymentWebhookMapper(), 'stripe'];
        yield 'paypal' => [new WebhookPayPalPaymentWebhookMapper(), 'paypal'];
        yield 'yookassa' => [new WebhookYooKassaPaymentWebhookMapper(), 'yookassa'];
    }

    #[DataProvider('nullableStatusMapperProvider')]
    public function testMapperReturnsNullWhenProviderStatusIsNotAvailable(
        PaymentWebhookMapperInterface $mapper,
        string $providerId,
    ): void {
        $payload = new WebhookPayload(
            providerId: $providerId,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment.succeeded',
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testRobokassaExtractsStatusFromSupportedResultUrlStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'robokassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'result_url',
        );

        $this->assertSame('result_url', $mapper->extractPaymentStatus($payload));
    }

    public function testRobokassaReturnsNullForMissingStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'robokassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: null,
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testRobokassaReturnsNullForAmbiguousStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: 'robokassa',
            eventType: null,
            providerEventType: 'ambiguous_callback',
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }
}
