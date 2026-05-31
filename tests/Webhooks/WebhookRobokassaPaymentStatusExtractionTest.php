<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPaymentWebhookMapper;

final class WebhookRobokassaPaymentStatusExtractionTest extends TestCase
{
    public function testExtractsPaymentStatusFromSupportedResultUrlCallbackSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );

        $this->assertSame(
            WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
            $mapper->extractPaymentStatus($payload),
        );
    }

    public function testExplicitParsedPaymentStatusHasPriorityOverCallbackSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
            data: ['OutSum' => '100.00'],
            paymentStatus: 'paid',
        );

        $this->assertSame('paid', $mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullForMissingCallbackStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: null,
            data: ['OutSum' => '100.00'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullForAmbiguousCallbackStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: null,
            providerEventType: 'ambiguous_callback',
            data: ['OutSum' => '100.00'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullForUnsupportedCallbackStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
            data: ['OutSum' => '100.00'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullForSupportedEventWithUnsupportedProviderCallbackType(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'success_url',
            data: ['OutSum' => '100.00'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }
}
