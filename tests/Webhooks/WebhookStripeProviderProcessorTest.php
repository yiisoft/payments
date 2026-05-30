<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookStripeProviderProcessor;

final class WebhookStripeProviderProcessorTest extends TestCase
{
    public function testImplementsProviderProcessorInterface(): void
    {
        $processor = new WebhookStripeProviderProcessor();

        $this->assertInstanceOf(WebhookProviderProcessorInterface::class, $processor);
    }

    public function testReturnsStripeProviderId(): void
    {
        $processor = new WebhookStripeProviderProcessor();

        $this->assertSame('stripe', $processor->getProviderId());
    }

    public function testProcessesSuccessfulStripePaymentWebhook(): void
    {
        $processor = new WebhookStripeProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded","data":{"object":{"id":"pi_123","status":"succeeded"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNotNull($result->rawData);
        $this->assertSame($input->rawBody, $result->rawData->rawBody);
        $this->assertSame($input->headers, $result->rawData->headers);
        $this->assertSame('payment_intent.succeeded', $result->rawData->providerEventType);
        $this->assertSame('succeeded', $result->rawData->payload['data']['object']['status']);
    }

    public function testReturnsUnsupportedEventForRecognizedButUnsupportedStripePaymentWebhook(): void
    {
        $processor = new WebhookStripeProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.processing","data":{"object":{"status":"processing"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentProcessing, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame('payment_intent.processing', $result->reason->providerEventType);
        $this->assertNotNull($result->rawData);
        $this->assertSame('payment_intent.processing', $result->rawData->providerEventType);
    }

    public function testReturnsUnknownEventForUnknownStripeProviderEventType(): void
    {
        $processor = new WebhookStripeProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.future_event","data":{"object":{"status":"unknown"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('payment_intent.future_event', $result->reason->providerEventType);
    }

    public function testReturnsUnknownEventWhenProviderEventTypeCannotBeRecognized(): void
    {
        $processor = new WebhookStripeProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"id":"evt_123","data":{"object":{"status":"unknown"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('', $result->reason->providerEventType);
    }
}
