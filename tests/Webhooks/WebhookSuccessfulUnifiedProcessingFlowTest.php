<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Tests\Webhooks\Support\SuccessfulWebhookProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;

final class WebhookSuccessfulUnifiedProcessingFlowTest extends TestCase
{
    public function testProcessorIsSelectedByInputProviderId(): void
    {
        $stripeProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            payload: ['type' => 'payment_intent.succeeded'],
        );
        $paypalProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: 'PAYMENT.CAPTURE.REFUNDED',
            payload: ['event_type' => 'PAYMENT.CAPTURE.REFUNDED'],
        );
        $robokassaProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'robokassa',
            eventType: WebhookEventType::PaymentFailed,
            providerEventType: 'ResultURL',
            payload: ['InvId' => '100500'],
        );
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry(
            $stripeProcessor,
            $paypalProcessor,
            $robokassaProcessor,
        ));
        $input = new WebhookInput(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.REFUNDED"}',
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            providerId: 'paypal',
        );

        $context = $processor->process($input);

        $this->assertInstanceOf(WebhookContext::class, $context);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $context->eventType);
        $this->assertNotNull($context->rawData);
        $this->assertSame('{"event_type":"PAYMENT.CAPTURE.REFUNDED"}', $context->rawData->rawBody);
        $this->assertSame(['PayPal-Transmission-Id' => 'transmission-id'], $context->rawData->headers);
        $this->assertSame(['event_type' => 'PAYMENT.CAPTURE.REFUNDED'], $context->rawData->payload);
        $this->assertSame('PAYMENT.CAPTURE.REFUNDED', $context->rawData->providerEventType);

        $this->assertSame(0, $stripeProcessor->processCalls);
        $this->assertNull($stripeProcessor->processedInput);
        $this->assertSame(1, $paypalProcessor->processCalls);
        $this->assertSame($input, $paypalProcessor->processedInput);
        $this->assertSame(0, $robokassaProcessor->processCalls);
        $this->assertNull($robokassaProcessor->processedInput);
    }

    public function testWebhookInputIsPassedToSelectedProcessorWithoutRawDataLoss(): void
    {
        $selectedProcessor = new SuccessfulWebhookProviderProcessor(providerId: 'stripe');
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($selectedProcessor));
        $input = new WebhookInput(
            rawBody: '{"id":"evt_123","data":{"object":{"id":"pi_123"}}}',
            headers: [
                'Stripe-Signature' => 't=1700000000,v1=signature',
                'X-Forwarded-For' => ['203.0.113.10', '198.51.100.20'],
            ],
            queryParams: [
                'endpoint' => 'payments',
                'debug' => '1',
            ],
            bodyParams: [
                'id' => 'evt_123',
                'data' => [
                    'object' => [
                        'id' => 'pi_123',
                    ],
                ],
            ],
            providerId: 'stripe',
        );

        $context = $processor->process($input);

        $this->assertInstanceOf(WebhookContext::class, $context);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame(1, $selectedProcessor->processCalls);
        $this->assertSame($input, $selectedProcessor->processedInput);
        $this->assertNotNull($selectedProcessor->processedInput);
        $this->assertSame($input->rawBody, $selectedProcessor->processedInput->rawBody);
        $this->assertSame($input->headers, $selectedProcessor->processedInput->headers);
        $this->assertSame($input->queryParams, $selectedProcessor->processedInput->queryParams);
        $this->assertSame($input->bodyParams, $selectedProcessor->processedInput->bodyParams);
        $this->assertSame($input->providerId, $selectedProcessor->processedInput->providerId);
        $this->assertNotNull($context->rawData);
        $this->assertSame($input->rawBody, $context->rawData->rawBody);
        $this->assertSame($input->headers, $context->rawData->headers);
    }

    public function testSuccessfulFlowReturnsWebhookContext(): void
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
        $this->assertSame('stripe', $context->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertNull($context->validationFailureReason);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNull($context->unknownEventReason);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame('{"type":"payment_intent.succeeded"}', $context->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 't=123,v1=signature'], $context->rawData->headers);
        $this->assertSame(['type' => 'payment_intent.succeeded'], $context->rawData->payload);
        $this->assertSame('payment_intent.succeeded', $context->rawData->providerEventType);
    }
}
