<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentMapper;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentMapper;

final class WebhookPaymentMapperStatusExtractionContractTest extends TestCase
{
    /**
     * @return iterable<string, array{WebhookPaymentMapperInterface, string, string}>
     */
    public static function providerStatusMapperProvider(): iterable
    {
        yield 'stripe' => [new WebhookStripePaymentMapper(), 'stripe', 'succeeded'];
        yield 'paypal' => [new WebhookPayPalPaymentMapper(), 'paypal', 'COMPLETED'];
        yield 'yookassa' => [new WebhookYooKassaPaymentMapper(), 'yookassa', 'succeeded'];
    }

    #[DataProvider('providerStatusMapperProvider')]
    public function testMapperExtractsProviderStatusStringWhenItIsAvailableInPayload(
        WebhookPaymentMapperInterface $mapper,
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
     * @return iterable<string, array{WebhookPaymentMapperInterface, string}>
     */
    public static function nullableStatusMapperProvider(): iterable
    {
        yield 'stripe' => [new WebhookStripePaymentMapper(), 'stripe'];
        yield 'paypal' => [new WebhookPayPalPaymentMapper(), 'paypal'];
        yield 'yookassa' => [new WebhookYooKassaPaymentMapper(), 'yookassa'];
    }

    #[DataProvider('nullableStatusMapperProvider')]
    public function testMapperReturnsNullWhenProviderStatusIsNotAvailable(
        WebhookPaymentMapperInterface $mapper,
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
        $mapper = new WebhookRobokassaPaymentMapper();
        $payload = new WebhookPayload(
            providerId: 'robokassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'result_url',
        );

        $this->assertSame('result_url', $mapper->extractPaymentStatus($payload));
    }

    public function testRobokassaReturnsNullForMissingStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentMapper();
        $payload = new WebhookPayload(
            providerId: 'robokassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: null,
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testRobokassaReturnsNullForAmbiguousStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentMapper();
        $payload = new WebhookPayload(
            providerId: 'robokassa',
            eventType: null,
            providerEventType: 'ambiguous_callback',
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }
}
