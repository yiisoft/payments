<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Tests\Webhooks\Support\ValidationFailedWebhookProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;

final class ValidationFailedWebhookProviderProcessorTest extends TestCase
{
    public function testProcessorImplementsProviderProcessorContract(): void
    {
        $processor = new ValidationFailedWebhookProviderProcessor('stripe');

        $this->assertInstanceOf(WebhookProviderProcessorInterface::class, $processor);
        $this->assertSame('stripe', $processor->getProviderId());
    }

    public function testProcessorReturnsValidationFailedResult(): void
    {
        $processor = new ValidationFailedWebhookProviderProcessor(
            providerId: 'stripe',
            providerEventType: 'payment_intent.succeeded',
            payload: ['type' => 'payment_intent.succeeded'],
        );
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
    }

    public function testProcessorKeepsProcessedInputForFlowAssertions(): void
    {
        $processor = new ValidationFailedWebhookProviderProcessor('stripe');
        $input = new WebhookInput(rawBody: '{}', providerId: 'stripe');

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $result->status);
        $this->assertSame(1, $processor->processCalls);
        $this->assertSame($input, $processor->processedInput);
    }
}
