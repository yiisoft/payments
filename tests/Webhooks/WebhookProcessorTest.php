<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Tests\Webhooks\Support\SuccessfulWebhookProviderProcessor;
use Yiisoft\Payments\Tests\Webhooks\Support\UnsupportedWebhookProviderProcessor;
use Yiisoft\Payments\Tests\Webhooks\Support\ValidationFailedWebhookProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProcessorInterface;
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
        $providerProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            payload: ['type' => 'payment_intent.succeeded'],
        );
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($providerProcessor));
        $input = new WebhookInput(
            rawBody: '{"type":"payment_intent.succeeded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNotNull($result->rawData);
        $this->assertSame('{"type":"payment_intent.succeeded"}', $result->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 't=123,v1=signature'], $result->rawData->headers);
        $this->assertSame(['type' => 'payment_intent.succeeded'], $result->rawData->payload);
        $this->assertSame('payment_intent.succeeded', $result->rawData->providerEventType);
        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
    }

    public function testProcessorUsesInputProviderIdForProviderProcessorResolution(): void
    {
        $stripeProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
        );
        $paypalProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'PAYMENT.CAPTURE.COMPLETED',
        );
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($stripeProcessor, $paypalProcessor));
        $input = new WebhookInput(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            providerId: 'paypal',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $result->rawData?->providerEventType);
        $this->assertSame(0, $stripeProcessor->processCalls);
        $this->assertNull($stripeProcessor->processedInput);
        $this->assertSame(1, $paypalProcessor->processCalls);
        $this->assertSame($input, $paypalProcessor->processedInput);
    }

    public function testProcessorReturnsUnsupportedCapabilityResultFromProviderProcessor(): void
    {
        $providerProcessor = new UnsupportedWebhookProviderProcessor(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: 'charge.refunded',
            payload: ['type' => 'charge.refunded'],
        );
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($providerProcessor));
        $input = new WebhookInput(
            rawBody: '{"type":"charge.refunded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame('charge.refunded', $result->reason->providerEventType);
        $this->assertNotNull($result->rawData);
        $this->assertSame('{"type":"charge.refunded"}', $result->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 't=123,v1=signature'], $result->rawData->headers);
        $this->assertSame(['type' => 'charge.refunded'], $result->rawData->payload);
        $this->assertSame('charge.refunded', $result->rawData->providerEventType);
        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
    }

    public function testProcessorReturnsValidationFailureResultFromProviderProcessor(): void
    {
        $providerProcessor = new ValidationFailedWebhookProviderProcessor(
            providerId: 'stripe',
            providerEventType: 'payment_intent.succeeded',
            payload: ['type' => 'payment_intent.succeeded'],
        );
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($providerProcessor));
        $input = new WebhookInput(
            rawBody: '{"type":"payment_intent.succeeded"}',
            headers: ['Stripe-Signature' => 'invalid-signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('validation_failed', $result->reason->code->value);
        $this->assertSame('payment_intent.succeeded', $result->reason->providerEventType);
        $this->assertNotNull($result->rawData);
        $this->assertSame('{"type":"payment_intent.succeeded"}', $result->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 'invalid-signature'], $result->rawData->headers);
        $this->assertSame(['type' => 'payment_intent.succeeded'], $result->rawData->payload);
        $this->assertSame('payment_intent.succeeded', $result->rawData->providerEventType);
        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
    }

    public function testProcessorReturnsMissingProviderProcessorResultWhenProcessorIsNotRegistered(): void
    {
        $registeredProcessor = new SuccessfulWebhookProviderProcessor('stripe');
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
}
