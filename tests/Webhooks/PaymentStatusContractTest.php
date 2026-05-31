<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;

final class PaymentStatusContractTest extends TestCase
{
    public function testWebhookPayloadCarriesProviderStatusStringWhenAvailable(): void
    {
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            data: ['status' => 'succeeded'],
            paymentStatus: 'succeeded',
        );

        $this->assertSame('succeeded', $payload->paymentStatus);
    }

    public function testWebhookPayloadDefaultsPaymentStatusToNull(): void
    {
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'CHECKOUT.ORDER.APPROVED',
        );

        $this->assertNull($payload->paymentStatus);
    }

    public function testMapperMayExposeProviderStatusStringWithoutNormalizingToDomainModel(): void
    {
        $mapper = new class implements WebhookPaymentMapperInterface {
            public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult
            {
                return WebhookProcessingResult::processed($payload->eventType ?? WebhookEventType::PaymentSucceeded);
            }

            public function extractPaymentStatus(WebhookPayload $payload): ?string
            {
                return $payload->paymentStatus;
            }
        };
        $payload = new WebhookPayload(
            providerId: 'yookassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment.succeeded',
            data: ['object' => ['status' => 'succeeded']],
            paymentStatus: 'succeeded',
        );

        $this->assertSame('succeeded', $mapper->extractPaymentStatus($payload));
    }

    public function testMapperReturnsNullForUnmappedProviderStatusInsteadOfUnknownSentinel(): void
    {
        $mapper = new class implements WebhookPaymentMapperInterface {
            public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult
            {
                return WebhookProcessingResult::processed($payload->eventType ?? WebhookEventType::PaymentSucceeded);
            }

            public function extractPaymentStatus(WebhookPayload $payload): ?string
            {
                return null;
            }
        };
        $payload = new WebhookPayload(
            providerId: 'acmepay',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment.completed',
            data: ['status' => 'provider-specific-unmapped-status'],
            paymentStatus: 'provider-specific-unmapped-status',
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testStatusContractDoesNotDefineDedicatedCommonPaymentStatusModel(): void
    {
        $this->assertFalse(class_exists('Yiisoft\\Payments\\Models\\PaymentStatus'));
        $this->assertFalse(enum_exists('Yiisoft\\Payments\\Models\\PaymentStatus'));
        $this->assertFalse(class_exists('Yiisoft\\Payments\\Webhooks\\PaymentStatus'));
        $this->assertFalse(enum_exists('Yiisoft\\Payments\\Webhooks\\PaymentStatus'));
        $this->assertFalse(class_exists('Yiisoft\\Payments\\Constants\\PaymentIntentStatus'));
        $this->assertFalse(enum_exists('Yiisoft\\Payments\\Constants\\PaymentIntentStatus'));
    }

    public function testStatusContractDoesNotDefineUnknownStatusSentinel(): void
    {
        $constants = (new ReflectionClass(PaymentIntent::class))->getConstants();

        $this->assertArrayNotHasKey('STATUS_UNKNOWN', $constants);
        $this->assertNotContains('unknown', $constants);
    }
}
