<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Tests\Webhooks\Support\SuccessfulWebhookProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;

final class WebhookMissingProcessorCapabilityFlowTest extends TestCase
{
    public function testMissingProviderProcessorReturnsValidationFailedContext(): void
    {
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry());
        $input = new WebhookInput(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            queryParams: ['source' => 'paypal-webhook'],
            bodyParams: ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'],
            providerId: 'paypal',
        );

        $context = $processor->process($input);

        $this->assertInstanceOf(WebhookContext::class, $context);
        $this->assertSame('paypal', $context->providerId);
        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame('missing_provider_processor', $context->validationFailureReason->code->value);
        $this->assertSame(
            'Webhook provider processor is not registered for provider "paypal".',
            $context->validationFailureReason->message,
        );
        $this->assertNull($context->validationFailureReason->providerEventType);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNull($context->unknownEventReason);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame($input->rawBody, $context->rawData->rawBody);
        $this->assertSame($input->headers, $context->rawData->headers);
        $this->assertNull($context->rawData->payload);
        $this->assertNull($context->rawData->providerEventType);
    }

    public function testMissingProviderProcessorDoesNotFallbackToAnotherRegisteredProcessor(): void
    {
        $registeredProcessor = new SuccessfulWebhookProviderProcessor(providerId: 'stripe');
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($registeredProcessor));
        $input = new WebhookInput(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            providerId: 'paypal',
        );

        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame('missing_provider_processor', $context->validationFailureReason->code->value);
        $this->assertSame(0, $registeredProcessor->processCalls);
        $this->assertNull($registeredProcessor->processedInput);
    }
}
