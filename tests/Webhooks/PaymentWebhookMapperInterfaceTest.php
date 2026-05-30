<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class PaymentWebhookMapperInterfaceTest extends TestCase
{
    public function testInterfaceExposesOnlyStableMapperContractMethods(): void
    {
        $reflection = new ReflectionClass(PaymentWebhookMapperInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertSame(
            [
                'mapPaymentWebhook',
                'extractPaymentStatus',
            ],
            array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ),
        );
    }

    public function testMapperContractMethodsArePublicInstanceMethods(): void
    {
        foreach (['mapPaymentWebhook', 'extractPaymentStatus'] as $methodName) {
            $method = new ReflectionMethod(PaymentWebhookMapperInterface::class, $methodName);

            $this->assertTrue($method->isPublic());
            $this->assertFalse($method->isStatic());
        }
    }

    public function testMapPaymentWebhookAcceptsPayloadAndReturnsProcessingResult(): void
    {
        $method = new ReflectionMethod(PaymentWebhookMapperInterface::class, 'mapPaymentWebhook');
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        $this->assertCount(1, $parameters);
        $this->assertSame('payload', $parameters[0]->getName());
        $this->assertFalse($parameters[0]->allowsNull());
        $this->assertSame(WebhookPayload::class, $parameters[0]->getType()?->getName());
        $this->assertNotNull($returnType);
        $this->assertFalse($returnType->allowsNull());
        $this->assertSame(WebhookProcessingResult::class, $returnType->getName());
    }

    public function testExtractPaymentStatusAcceptsPayloadAndReturnsNullableString(): void
    {
        $method = new ReflectionMethod(PaymentWebhookMapperInterface::class, 'extractPaymentStatus');
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        $this->assertCount(1, $parameters);
        $this->assertSame('payload', $parameters[0]->getName());
        $this->assertFalse($parameters[0]->allowsNull());
        $this->assertSame(WebhookPayload::class, $parameters[0]->getType()?->getName());
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull());
        $this->assertSame('string', $returnType->getName());
    }

    public function testMapperCanMapPaymentPayloadIntoProcessingResult(): void
    {
        $mapper = new class implements PaymentWebhookMapperInterface {
            public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult
            {
                return new WebhookProcessingResult(
                    status: WebhookProcessingStatus::Processed,
                    eventType: $payload->eventType,
                    rawData: $payload->rawData,
                );
            }

            public function extractPaymentStatus(WebhookPayload $payload): ?string
            {
                return $payload->paymentStatus;
            }
        };

        $rawData = new WebhookRawData(
            rawBody: '{"id":"evt_123"}',
            headers: ['Stripe-Signature' => 't=123,v1=abc'],
            payload: ['id' => 'evt_123'],
            providerEventType: 'payment_intent.succeeded',
        );
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            data: ['id' => 'evt_123'],
            paymentStatus: 'succeeded',
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertSame($rawData, $result->rawData);
        $this->assertSame('succeeded', $mapper->extractPaymentStatus($payload));
    }

    public function testMapperCanReturnNullWhenPaymentStatusIsNotAvailable(): void
    {
        $mapper = new class implements PaymentWebhookMapperInterface {
            public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult
            {
                return new WebhookProcessingResult(
                    status: WebhookProcessingStatus::Processed,
                    eventType: $payload->eventType,
                    rawData: $payload->rawData,
                );
            }

            public function extractPaymentStatus(WebhookPayload $payload): ?string
            {
                return $payload->paymentStatus;
            }
        };

        $payload = new WebhookPayload(
            providerId: 'robokassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'result_url',
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testMapperReturnsNullForUnmappedProviderStatus(): void
    {
        $mapper = new class implements PaymentWebhookMapperInterface {
            public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult
            {
                return new WebhookProcessingResult(
                    status: WebhookProcessingStatus::Processed,
                    eventType: $payload->eventType,
                    rawData: $payload->rawData,
                );
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
            data: ['provider_status' => 'completed_with_unmapped_provider_specific_state'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }
}
