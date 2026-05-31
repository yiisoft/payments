<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookPayload;

final class PaymentStatusContractDiscoveryTest extends TestCase
{
    public function testPaymentIntentUsesNullableStringStatus(): void
    {
        $property = new ReflectionProperty(PaymentIntent::class, 'status');
        $type = $property->getType();

        $this->assertTrue($property->isPublic());
        $this->assertTrue($property->isReadOnly());
        $this->assertNotNull($type);
        $this->assertTrue($type->allowsNull());
        $this->assertSame('string', $type->getName());
    }

    public function testPaymentIntentExposesStringStatusConstants(): void
    {
        $reflection = new ReflectionClass(PaymentIntent::class);

        $this->assertSame(
            [
                'STATUS_REQUIRES_PAYMENT_METHOD' => 'requires_payment_method',
                'STATUS_REQUIRES_CONFIRMATION' => 'requires_confirmation',
                'STATUS_REQUIRES_ACTION' => 'requires_action',
                'STATUS_PROCESSING' => 'processing',
                'STATUS_REQUIRES_CAPTURE' => 'requires_capture',
                'STATUS_CANCELED' => 'canceled',
                'STATUS_SUCCEEDED' => 'succeeded',
            ],
            array_intersect_key(
                $reflection->getConstants(),
                array_flip([
                    'STATUS_REQUIRES_PAYMENT_METHOD',
                    'STATUS_REQUIRES_CONFIRMATION',
                    'STATUS_REQUIRES_ACTION',
                    'STATUS_PROCESSING',
                    'STATUS_REQUIRES_CAPTURE',
                    'STATUS_CANCELED',
                    'STATUS_SUCCEEDED',
                ]),
            ),
        );
    }

    public function testR1DoesNotIntroduceDedicatedPaymentStatusDomainModel(): void
    {
        $this->assertFalse(class_exists('Yiisoft\Payments\Models\PaymentStatus'));
        $this->assertFalse(enum_exists('Yiisoft\Payments\Models\PaymentStatus'));
        $this->assertFalse(class_exists('Yiisoft\Payments\Webhooks\PaymentStatus'));
        $this->assertFalse(enum_exists('Yiisoft\Payments\Webhooks\PaymentStatus'));
        $this->assertFalse(class_exists('Yiisoft\Payments\Constants\PaymentIntentStatus'));
        $this->assertFalse(enum_exists('Yiisoft\Payments\Constants\PaymentIntentStatus'));
    }

    public function testWebhookPayloadCarriesNullableProviderPaymentStatus(): void
    {
        $property = new ReflectionProperty(WebhookPayload::class, 'paymentStatus');
        $type = $property->getType();

        $this->assertTrue($property->isPublic());
        $this->assertTrue($property->isReadOnly());
        $this->assertNotNull($type);
        $this->assertTrue($type->allowsNull());
        $this->assertSame('string', $type->getName());
    }

    public function testR1DoesNotIntroduceUnknownPaymentStatusSentinel(): void
    {
        $reflection = new ReflectionClass(PaymentIntent::class);
        $constants = $reflection->getConstants();

        $this->assertArrayNotHasKey('STATUS_UNKNOWN', $constants);
        $this->assertNotContains('unknown', $constants);
    }

    public function testWebhookPayloadRepresentsUnavailableOrUnmappedPaymentStatusAsNull(): void
    {
        $payload = new WebhookPayload(
            providerId: 'acmepay',
            data: ['provider_status' => 'unmapped_provider_status'],
        );

        $this->assertNull($payload->paymentStatus);
    }

    public function testMapperStatusExtractionReturnsMinimalR1NullableStringRepresentation(): void
    {
        $method = new ReflectionMethod(PaymentWebhookMapperInterface::class, 'extractPaymentStatus');
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        $this->assertCount(1, $parameters);
        $this->assertSame(WebhookPayload::class, $parameters[0]->getType()?->getName());
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull());
        $this->assertSame('string', $returnType->getName());
    }
}
