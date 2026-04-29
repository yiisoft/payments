<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessorInterface;

final class WebhookProcessorInterfaceTest extends TestCase
{
    public function testProcessReturnTypeIsFixedToWebhookProcessingResult(): void
    {
        $method = new ReflectionMethod(WebhookProcessorInterface::class, 'process');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertFalse($returnType->allowsNull());
        $this->assertSame(WebhookProcessingResult::class, $returnType->getName());
    }
}
