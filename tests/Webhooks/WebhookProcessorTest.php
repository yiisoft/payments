<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;

final class WebhookProcessorTest extends TestCase
{
    public function testProcessorIsCommonWebhookProcessingService(): void
    {
        $reflection = new ReflectionClass(WebhookProcessor::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->implementsInterface(WebhookProcessorInterface::class));
    }

    public function testProcessorDependsOnProviderProcessorRegistry(): void
    {
        $constructor = new ReflectionClass(WebhookProcessor::class)->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertSame('providerProcessorRegistry', $constructor->getParameters()[0]->getName());
        $this->assertSame(WebhookProviderProcessorRegistry::class, $constructor->getParameters()[0]->getType()?->getName());
        $this->assertFalse($constructor->getParameters()[0]->getType()?->allowsNull());
    }

    public function testProcessorCanBeInstantiatedWithRegistry(): void
    {
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry());

        $this->assertInstanceOf(WebhookProcessorInterface::class, $processor);
    }
}
