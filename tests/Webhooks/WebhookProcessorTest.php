<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;

final class WebhookProcessorTest extends TestCase
{
    public function testProcessorIsCommonWebhookProcessingService(): void
    {
        $reflection = new ReflectionClass(WebhookProcessor::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->implementsInterface(WebhookProcessorInterface::class));
    }

    public function testProcessorDependsOnProviderProcessorRegistry(): void
    {
        $constructor = new ReflectionClass(WebhookProcessor::class)->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertSame('providerProcessorRegistry', $constructor->getParameters()[0]->getName());
        $this->assertSame(WebhookProviderProcessorRegistry::class, $constructor->getParameters()[0]->getType()?->getName());
        $this->assertFalse($constructor->getParameters()[0]->getType()?->allowsNull());
    }

    public function testProcessorCanBeInstantiatedWithRegistry(): void
    {
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry());

        $this->assertInstanceOf(WebhookProcessorInterface::class, $processor);
    }

    public function testProcessorDelegatesInputToResolvedProviderProcessor(): void
    {
        $expectedResult = new WebhookProcessingResult(WebhookProcessingStatus::Processed);
        $providerProcessor = $this->createTrackingProviderProcessor('stripe', $expectedResult);
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($providerProcessor));
        $input = new WebhookInput(
            rawBody: '{"type":"payment_intent.succeeded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame($expectedResult, $result);
        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
    }

    public function testProcessorUsesInputProviderIdForProviderProcessorResolution(): void
    {
        $stripeResult = new WebhookProcessingResult(WebhookProcessingStatus::Processed);
        $paypalResult = new WebhookProcessingResult(WebhookProcessingStatus::UnsupportedEvent);
        $stripeProcessor = $this->createTrackingProviderProcessor('stripe', $stripeResult);
        $paypalProcessor = $this->createTrackingProviderProcessor('paypal', $paypalResult);
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($stripeProcessor, $paypalProcessor));

        $result = $processor->process(new WebhookInput(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            providerId: 'paypal',
        ));

        $this->assertSame($paypalResult, $result);
        $this->assertSame(0, $stripeProcessor->processCalls);
        $this->assertNull($stripeProcessor->processedInput);
        $this->assertSame(1, $paypalProcessor->processCalls);
    }

    public function testProcessorReturnsUnsupportedCapabilityResultFromProviderProcessor(): void
    {
        $rawData = new WebhookRawData(
            rawBody: '{"type":"charge.refunded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: ['type' => 'charge.refunded'],
            providerEventType: 'charge.refunded',
        );
        $expectedResult = WebhookProcessingResult::unsupportedEvent(
            WebhookEventType::PaymentRefunded,
            'charge.refunded',
            $rawData,
        );
        $providerProcessor = $this->createTrackingProviderProcessor('stripe', $expectedResult);
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($providerProcessor));
        $input = new WebhookInput(
            rawBody: '{"type":"charge.refunded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame($expectedResult, $result);
        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame('charge.refunded', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
    }

    public function testProcessorReturnsMissingProviderProcessorResultWhenProcessorIsNotRegistered(): void
    {
        $registeredProcessor = $this->createTrackingProviderProcessor(
            'stripe',
            new WebhookProcessingResult(WebhookProcessingStatus::Processed),
        );
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($registeredProcessor));

        $result = $processor->process(new WebhookInput(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            providerId: 'paypal',
        ));

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('missing_provider_processor', $result->reason->code->value);
        $this->assertSame(
            'Webhook provider processor is not registered for provider "paypal".',
            $result->reason->message,
        );
        $this->assertNotNull($result->rawData);
        $this->assertSame('{"event_type":"PAYMENT.CAPTURE.COMPLETED"}', $result->rawData->rawBody);
        $this->assertSame(['PayPal-Transmission-Id' => 'transmission-id'], $result->rawData->headers);
        $this->assertNull($result->rawData->payload);
        $this->assertNull($result->rawData->providerEventType);
        $this->assertSame(0, $registeredProcessor->processCalls);
        $this->assertNull($registeredProcessor->processedInput);
    }

    /**
     * @return WebhookProviderProcessorInterface&object{processCalls:int,processedInput:?WebhookInput}
     */
    private function createTrackingProviderProcessor(
        string $providerId,
        WebhookProcessingResult $result,
    ): WebhookProviderProcessorInterface {
        return new class ($providerId, $result) implements WebhookProviderProcessorInterface {
            public int $processCalls = 0;
            public ?WebhookInput $processedInput = null;

            public function __construct(
                private readonly string $providerId,
                private readonly WebhookProcessingResult $result,
            ) {
            }

            public function getProviderId(): string
            {
                return $this->providerId;
            }

            public function process(WebhookInput $input): WebhookProcessingResult
            {
                $this->processCalls++;
                $this->processedInput = $input;

                return $this->result;
            }
        };
    }
}
