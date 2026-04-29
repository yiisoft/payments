<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookContextTest extends TestCase
{
    public function testContextKeepsNormalizedWebhookData(): void
    {
        $validationFailureReason = new WebhookReason(
            code: new WebhookReasonCode('invalid_signature'),
            message: 'Webhook signature is invalid.',
            providerEventType: 'payment_intent.succeeded',
        );
        $unsupportedEventReason = new WebhookReason(
            code: new WebhookReasonCode('unsupported_event_type'),
            message: 'Webhook event type is recognized but is not supported by the current webhook contract.',
            providerEventType: 'charge.dispute.created',
        );
        $unknownEventReason = new WebhookReason(
            code: new WebhookReasonCode('unknown_event_type'),
            message: 'Provider event type is not recognized by the webhook event mapping.',
            providerEventType: 'invoice.unmapped_event',
        );

        $rawInput = new WebhookInput(
            rawBody: '{"id":"evt_123"}',
            headers: ['Stripe-Signature' => 'test-signature'],
            providerId: 'stripe',
        );
        $rawData = new WebhookRawData(
            rawBody: '{"id":"evt_123"}',
            headers: ['Stripe-Signature' => 'test-signature'],
            payload: ['id' => 'evt_123'],
            providerEventType: 'payment_intent.succeeded',
        );

        $context = new WebhookContext(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            status: WebhookProcessingStatus::Processed,
            validationFailureReason: $validationFailureReason,
            unsupportedEventReason: $unsupportedEventReason,
            unknownEventReason: $unknownEventReason,
            rawInput: $rawInput,
            rawData: $rawData,
        );

        $this->assertSame('stripe', $context->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame($validationFailureReason, $context->validationFailureReason);
        $this->assertSame($unsupportedEventReason, $context->unsupportedEventReason);
        $this->assertSame($unknownEventReason, $context->unknownEventReason);
        $this->assertSame($rawInput, $context->rawInput);
        $this->assertSame($rawData, $context->rawData);
    }

    public function testContextCanBeCreatedWithoutNormalizedEventDataYet(): void
    {
        $context = new WebhookContext();

        $this->assertNull($context->providerId);
        $this->assertNull($context->eventType);
        $this->assertNull($context->status);
        $this->assertNull($context->validationFailureReason);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNull($context->unknownEventReason);
        $this->assertNull($context->rawInput);
        $this->assertNull($context->rawData);
    }

    public function testContextIsImmutableValueObject(): void
    {
        $reflection = new ReflectionClass(WebhookContext::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->getProperty('providerId')->isReadOnly());
        $this->assertTrue($reflection->getProperty('eventType')->isReadOnly());
        $this->assertTrue($reflection->getProperty('status')->isReadOnly());
        $this->assertTrue($reflection->getProperty('validationFailureReason')->isReadOnly());
        $this->assertTrue($reflection->getProperty('unsupportedEventReason')->isReadOnly());
        $this->assertTrue($reflection->getProperty('unknownEventReason')->isReadOnly());
        $this->assertTrue($reflection->getProperty('rawInput')->isReadOnly());
        $this->assertTrue($reflection->getProperty('rawData')->isReadOnly());
    }
}
