<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Tests\Webhooks\Support\FailedWebhookProviderValidator;
use Yiisoft\Payments\Tests\Webhooks\Support\SuccessfulWebhookProviderProcessor;
use Yiisoft\Payments\Tests\Webhooks\Support\SuccessfulWebhookProviderValidator;
use Yiisoft\Payments\Tests\Webhooks\Support\UnsupportedWebhookProviderProcessor;
use Yiisoft\Payments\Tests\Webhooks\Support\ValidationFailedWebhookProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorRegistry;

final class WebhookProcessorTest extends TestCase
{
    public function testProcessorIsCommonWebhookProcessingService(): void
    {
        $reflection = new ReflectionClass(WebhookProcessor::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->implementsInterface(WebhookProcessorInterface::class));
    }

    public function testProcessorDependsOnProviderProcessorAndValidatorRegistries(): void
    {
        $constructor = new ReflectionClass(WebhookProcessor::class)->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(2, $constructor->getNumberOfParameters());
        $this->assertSame('providerProcessorRegistry', $constructor->getParameters()[0]->getName());
        $this->assertSame(WebhookProviderProcessorRegistry::class, $constructor->getParameters()[0]->getType()?->getName());
        $this->assertFalse($constructor->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame('providerValidatorRegistry', $constructor->getParameters()[1]->getName());
        $this->assertSame(WebhookProviderValidatorRegistry::class, $constructor->getParameters()[1]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[1]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[1]->getDefaultValue());
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

        $context = $processor->process($input);

        $this->assertInstanceOf(WebhookContext::class, $context);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
        $this->assertNotNull($context->rawData);
        $this->assertSame('{"type":"payment_intent.succeeded"}', $context->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 't=123,v1=signature'], $context->rawData->headers);
        $this->assertSame(['type' => 'payment_intent.succeeded'], $context->rawData->payload);
        $this->assertSame('payment_intent.succeeded', $context->rawData->providerEventType);
        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
    }

    public function testProcessorCallsProviderValidatorBeforeProviderProcessor(): void
    {
        $providerValidator = new SuccessfulWebhookProviderValidator('stripe');
        $providerProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            payload: ['type' => 'payment_intent.succeeded'],
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry($providerProcessor),
            new WebhookProviderValidatorRegistry($providerValidator),
        );
        $input = new WebhookInput(
            rawBody: '{"type":"payment_intent.succeeded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame(1, $providerValidator->validateCalls);
        $this->assertSame($input, $providerValidator->validatedInput);
        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
    }

    public function testProcessorDoesNotCallProviderProcessorWhenProviderValidatorFails(): void
    {
        $providerValidator = new FailedWebhookProviderValidator('stripe');
        $providerProcessor = new SuccessfulWebhookProviderProcessor('stripe');
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry($providerProcessor),
            new WebhookProviderValidatorRegistry($providerValidator),
        );
        $input = new WebhookInput(
            rawBody: '{"type":"payment_intent.succeeded"}',
            headers: ['Stripe-Signature' => 'invalid-signature'],
            queryParams: ['source' => 'stripe-webhook'],
            bodyParams: ['form_field' => 'raw-value'],
            providerId: 'stripe',
        );

        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame('test_validation_failed', $context->validationFailureReason->code->value);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame('{"type":"payment_intent.succeeded"}', $context->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 'invalid-signature'], $context->rawData->headers);
        $this->assertSame(['source' => 'stripe-webhook'], $context->rawData->queryParams);
        $this->assertSame(['form_field' => 'raw-value'], $context->rawData->bodyParams);
        $this->assertNull($context->rawData->payload);
        $this->assertNull($context->rawData->providerEventType);
        $this->assertSame(1, $providerValidator->validateCalls);
        $this->assertSame($input, $providerValidator->validatedInput);
        $this->assertSame(0, $providerProcessor->processCalls);
        $this->assertNull($providerProcessor->processedInput);
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

        $context = $processor->process($input);

        $this->assertInstanceOf(WebhookContext::class, $context);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $context->rawData?->providerEventType);
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

        $context = $processor->process($input);

        $this->assertInstanceOf(WebhookContext::class, $context);
        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $context->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $context->eventType);
        $this->assertNotNull($context->unsupportedEventReason);
        $this->assertSame('unsupported_event_type', $context->unsupportedEventReason->code->value);
        $this->assertSame('charge.refunded', $context->unsupportedEventReason->providerEventType);
        $this->assertNotNull($context->rawData);
        $this->assertSame('{"type":"charge.refunded"}', $context->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 't=123,v1=signature'], $context->rawData->headers);
        $this->assertSame(['type' => 'charge.refunded'], $context->rawData->payload);
        $this->assertSame('charge.refunded', $context->rawData->providerEventType);
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

        $context = $processor->process($input);

        $this->assertInstanceOf(WebhookContext::class, $context);
        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame('validation_failed', $context->validationFailureReason->code->value);
        $this->assertSame('payment_intent.succeeded', $context->validationFailureReason->providerEventType);
        $this->assertNotNull($context->rawData);
        $this->assertSame('{"type":"payment_intent.succeeded"}', $context->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 'invalid-signature'], $context->rawData->headers);
        $this->assertSame(['type' => 'payment_intent.succeeded'], $context->rawData->payload);
        $this->assertSame('payment_intent.succeeded', $context->rawData->providerEventType);
        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
    }

    public function testProcessorReturnsMissingProviderProcessorResultWhenProcessorIsNotRegistered(): void
    {
        $registeredProcessor = new SuccessfulWebhookProviderProcessor('stripe');
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($registeredProcessor));

        $context = $processor->process(new WebhookInput(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            queryParams: ['source' => 'paypal-webhook'],
            bodyParams: ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'],
            providerId: 'paypal',
        ));

        $this->assertInstanceOf(WebhookContext::class, $context);
        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame('missing_provider_processor', $context->validationFailureReason->code->value);
        $this->assertSame(
            'Webhook provider processor is not registered for provider "paypal".',
            $context->validationFailureReason->message,
        );
        $this->assertNotNull($context->rawData);
        $this->assertSame('{"event_type":"PAYMENT.CAPTURE.COMPLETED"}', $context->rawData->rawBody);
        $this->assertSame(['PayPal-Transmission-Id' => 'transmission-id'], $context->rawData->headers);
        $this->assertSame(['source' => 'paypal-webhook'], $context->rawData->queryParams);
        $this->assertSame(['event_type' => 'PAYMENT.CAPTURE.COMPLETED'], $context->rawData->bodyParams);
        $this->assertNull($context->rawData->payload);
        $this->assertNull($context->rawData->providerEventType);
        $this->assertSame(0, $registeredProcessor->processCalls);
        $this->assertNull($registeredProcessor->processedInput);
    }
}
