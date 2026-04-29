<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Tests\Webhooks\Support\SuccessfulWebhookProviderProcessor;
use Yiisoft\Payments\Tests\Webhooks\Support\UnsupportedWebhookProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;

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

    public function testExistingProviderWithoutWebhookCapabilityReturnsUnsupportedContext(): void
    {
        $providerProcessor = new class implements WebhookProviderProcessorInterface {
            public int $processCalls = 0;
            public ?WebhookInput $processedInput = null;

            public function getProviderId(): string
            {
                return 'paypal';
            }

            public function process(WebhookInput $input): WebhookProcessingResult
            {
                $this->processCalls++;
                $this->processedInput = $input;

                return new WebhookProcessingResult(
                    status: WebhookProcessingStatus::UnsupportedEvent,
                    reason: new WebhookReason(
                        code: new WebhookReasonCode('missing_webhook_capability'),
                        message: 'Webhook capability is not declared for the provider webhook event.',
                        providerEventType: 'PAYMENT.CAPTURE.PENDING',
                    ),
                    rawData: new WebhookRawData(
                        rawBody: $input->rawBody,
                        headers: $input->headers,
                        payload: ['event_type' => 'PAYMENT.CAPTURE.PENDING'],
                        providerEventType: 'PAYMENT.CAPTURE.PENDING',
                    ),
                );
            }
        };
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($providerProcessor));
        $input = new WebhookInput(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.PENDING"}',
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            providerId: 'paypal',
        );

        $context = $processor->process($input);

        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
        $this->assertSame('paypal', $context->providerId);
        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNull($context->validationFailureReason);
        $this->assertNotNull($context->unsupportedEventReason);
        $this->assertSame('missing_webhook_capability', $context->unsupportedEventReason->code->value);
        $this->assertSame(
            'Webhook capability is not declared for the provider webhook event.',
            $context->unsupportedEventReason->message,
        );
        $this->assertSame('PAYMENT.CAPTURE.PENDING', $context->unsupportedEventReason->providerEventType);
        $this->assertNull($context->unknownEventReason);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame($input->rawBody, $context->rawData->rawBody);
        $this->assertSame($input->headers, $context->rawData->headers);
        $this->assertSame(['event_type' => 'PAYMENT.CAPTURE.PENDING'], $context->rawData->payload);
        $this->assertSame('PAYMENT.CAPTURE.PENDING', $context->rawData->providerEventType);
    }

    public function testExistingProviderWithUnsupportedEventReturnsUnsupportedContext(): void
    {
        $providerProcessor = new UnsupportedWebhookProviderProcessor(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: 'charge.dispute.created',
            payload: ['type' => 'charge.dispute.created'],
        );
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($providerProcessor));
        $input = new WebhookInput(
            rawBody: '{"type":"charge.dispute.created"}',
            headers: ['Stripe-Signature' => 'signature'],
            providerId: 'stripe',
        );

        $context = $processor->process($input);

        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
        $this->assertSame('stripe', $context->providerId);
        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $context->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $context->eventType);
        $this->assertNull($context->validationFailureReason);
        $this->assertNotNull($context->unsupportedEventReason);
        $this->assertSame('unsupported_event_type', $context->unsupportedEventReason->code->value);
        $this->assertSame(
            'Webhook event type is recognized but is not supported by the current webhook contract.',
            $context->unsupportedEventReason->message,
        );
        $this->assertSame('charge.dispute.created', $context->unsupportedEventReason->providerEventType);
        $this->assertNull($context->unknownEventReason);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame($input->rawBody, $context->rawData->rawBody);
        $this->assertSame($input->headers, $context->rawData->headers);
        $this->assertSame(['type' => 'charge.dispute.created'], $context->rawData->payload);
        $this->assertSame('charge.dispute.created', $context->rawData->providerEventType);
    }

    public function testExistingProviderWithUnknownEventReturnsUnknownContext(): void
    {
        $providerProcessor = new class implements WebhookProviderProcessorInterface {
            public int $processCalls = 0;
            public ?WebhookInput $processedInput = null;

            public function getProviderId(): string
            {
                return 'stripe';
            }

            public function process(WebhookInput $input): WebhookProcessingResult
            {
                $this->processCalls++;
                $this->processedInput = $input;

                return new WebhookProcessingResult(
                    status: WebhookProcessingStatus::UnknownEvent,
                    reason: new WebhookReason(
                        code: new WebhookReasonCode('unknown_event_type'),
                        message: 'Provider event type is not recognized by the webhook event mapping.',
                        providerEventType: 'payment_intent.processing',
                    ),
                    rawData: new WebhookRawData(
                        rawBody: $input->rawBody,
                        headers: $input->headers,
                        payload: ['type' => 'payment_intent.processing'],
                        providerEventType: 'payment_intent.processing',
                    ),
                );
            }
        };
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($providerProcessor));
        $input = new WebhookInput(
            rawBody: '{"type":"payment_intent.processing"}',
            headers: ['Stripe-Signature' => 'signature'],
            providerId: 'stripe',
        );

        $context = $processor->process($input);

        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
        $this->assertSame('stripe', $context->providerId);
        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNull($context->validationFailureReason);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNotNull($context->unknownEventReason);
        $this->assertSame('unknown_event_type', $context->unknownEventReason->code->value);
        $this->assertSame(
            'Provider event type is not recognized by the webhook event mapping.',
            $context->unknownEventReason->message,
        );
        $this->assertSame('payment_intent.processing', $context->unknownEventReason->providerEventType);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame($input->rawBody, $context->rawData->rawBody);
        $this->assertSame($input->headers, $context->rawData->headers);
        $this->assertSame(['type' => 'payment_intent.processing'], $context->rawData->payload);
        $this->assertSame('payment_intent.processing', $context->rawData->providerEventType);
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
