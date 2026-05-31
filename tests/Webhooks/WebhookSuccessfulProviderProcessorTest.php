<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Tests\Webhooks\Support\WebhookSuccessfulProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;

final class WebhookSuccessfulProviderProcessorTest extends TestCase
{
    public function testProcessorImplementsProviderProcessorContract(): void
    {
        $processor = new WebhookSuccessfulProviderProcessor('stripe');

        $this->assertInstanceOf(WebhookProviderProcessorInterface::class, $processor);
        $this->assertSame('stripe', $processor->getProviderId());
    }

    public function testProcessorReturnsSuccessfulProcessedResult(): void
    {
        $processor = new WebhookSuccessfulProviderProcessor(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            payload: ['type' => 'payment_intent.succeeded'],
        );
        $input = new WebhookInput(
            rawBody: '{"type":"payment_intent.succeeded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNotNull($result->rawData);
        $this->assertSame('{"type":"payment_intent.succeeded"}', $result->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 't=123,v1=signature'], $result->rawData->headers);
        $this->assertSame(['type' => 'payment_intent.succeeded'], $result->rawData->payload);
        $this->assertSame('payment_intent.succeeded', $result->rawData->providerEventType);
    }

    public function testProcessorKeepsProcessedInputForFlowAssertions(): void
    {
        $processor = new WebhookSuccessfulProviderProcessor('stripe');
        $input = new WebhookInput(rawBody: '{}', providerId: 'stripe');

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(1, $processor->processCalls);
        $this->assertSame($input, $processor->processedInput);
    }
}
