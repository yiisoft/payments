<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;

final class WebhookContextTest extends TestCase
{
    public function testContextKeepsNormalizedWebhookData(): void
    {
        $context = new WebhookContext(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            status: WebhookProcessingStatus::Processed,
        );

        $this->assertSame('stripe', $context->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
    }

    public function testContextCanBeCreatedWithoutNormalizedEventDataYet(): void
    {
        $context = new WebhookContext();

        $this->assertNull($context->providerId);
        $this->assertNull($context->eventType);
        $this->assertNull($context->status);
    }

    public function testContextIsImmutableValueObject(): void
    {
        $reflection = new ReflectionClass(WebhookContext::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->getProperty('providerId')->isReadOnly());
        $this->assertTrue($reflection->getProperty('eventType')->isReadOnly());
        $this->assertTrue($reflection->getProperty('status')->isReadOnly());
    }
}
