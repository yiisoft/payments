<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;

final class WebhookProviderProcessorInterfaceTest extends TestCase
{
    public function testProviderProcessorExposesProviderIdAndProcessingResult(): void
    {
        $processor = new class implements WebhookProviderProcessorInterface {
            public function getProviderId(): string
            {
                return 'stripe';
            }

            public function process(WebhookInput $input): WebhookProcessingResult
            {
                return new WebhookProcessingResult(WebhookProcessingStatus::Processed);
            }
        };

        $this->assertSame('stripe', $processor->getProviderId());
        $this->assertSame(WebhookProcessingStatus::Processed, $processor->process(new WebhookInput('{}'))->status);
    }

    public function testProviderIdReturnTypeIsFixedToString(): void
    {
        $method = new ReflectionMethod(WebhookProviderProcessorInterface::class, 'getProviderId');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertFalse($returnType->allowsNull());
        $this->assertSame('string', $returnType->getName());
        $this->assertSame(0, $method->getNumberOfParameters());
    }

    public function testProcessSignatureReturnsProviderProcessingResult(): void
    {
        $method = new ReflectionMethod(WebhookProviderProcessorInterface::class, 'process');
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        $this->assertCount(1, $parameters);
        $this->assertSame('input', $parameters[0]->getName());
        $this->assertFalse($parameters[0]->allowsNull());
        $this->assertSame(WebhookInput::class, $parameters[0]->getType()?->getName());
        $this->assertNotNull($returnType);
        $this->assertFalse($returnType->allowsNull());
        $this->assertSame(WebhookProcessingResult::class, $returnType->getName());
    }
}
