<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Tests\Webhooks\Support\WebhookUnsupportedProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;

final class WebhookUnsupportedProviderProcessorTest extends TestCase
{
    public function testProcessorImplementsProviderProcessorContract(): void
    {
        $processor = new WebhookUnsupportedProviderProcessor('stripe');

        $this->assertInstanceOf(WebhookProviderProcessorInterface::class, $processor);
        $this->assertSame('stripe', $processor->getProviderId());
    }

    public function testProcessorReturnsUnsupportedEventResult(): void
    {
        $processor = new WebhookUnsupportedProviderProcessor(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: 'charge.dispute.created',
            payload: ['type' => 'charge.dispute.created'],
        );
        $input = new WebhookInput(
            rawBody: '{"type":"charge.dispute.created"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame('charge.dispute.created', $result->reason->providerEventType);
        $this->assertNotNull($result->rawData);
        $this->assertSame('{"type":"charge.dispute.created"}', $result->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 't=123,v1=signature'], $result->rawData->headers);
        $this->assertSame(['type' => 'charge.dispute.created'], $result->rawData->payload);
        $this->assertSame('charge.dispute.created', $result->rawData->providerEventType);
    }

    public function testProcessorKeepsProcessedInputForFlowAssertions(): void
    {
        $processor = new WebhookUnsupportedProviderProcessor('stripe');
        $input = new WebhookInput(rawBody: '{}', providerId: 'stripe');

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(1, $processor->processCalls);
        $this->assertSame($input, $processor->processedInput);
    }
}
