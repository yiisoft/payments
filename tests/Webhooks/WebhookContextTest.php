<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;

final class WebhookContextTest extends TestCase
{
    public function testContextKeepsNormalizedWebhookData(): void
    {
        $validationFailureReason = new WebhookReason(
            code: new WebhookReasonCode('invalid_signature'),
            message: 'Webhook signature is invalid.',
            providerEventType: 'payment_intent.succeeded',
        );

        $context = new WebhookContext(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            status: WebhookProcessingStatus::Processed,
            validationFailureReason: $validationFailureReason,
        );

        $this->assertSame('stripe', $context->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame($validationFailureReason, $context->validationFailureReason);
    }

    public function testContextCanBeCreatedWithoutNormalizedEventDataYet(): void
    {
        $context = new WebhookContext();

        $this->assertNull($context->providerId);
        $this->assertNull($context->eventType);
        $this->assertNull($context->status);
        $this->assertNull($context->validationFailureReason);
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
    }
}
