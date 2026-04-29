<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookEventType;

final class WebhookContextTest extends TestCase
{
    public function testContextKeepsNormalizedWebhookData(): void
    {
        $context = new WebhookContext(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
        );

        $this->assertSame('stripe', $context->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
    }

    public function testContextCanBeCreatedWithoutNormalizedEventDataYet(): void
    {
        $context = new WebhookContext();

        $this->assertNull($context->providerId);
        $this->assertNull($context->eventType);
    }

    public function testContextIsImmutableValueObject(): void
    {
        $reflection = new ReflectionClass(WebhookContext::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->getProperty('providerId')->isReadOnly());
        $this->assertTrue($reflection->getProperty('eventType')->isReadOnly());
    }
}
