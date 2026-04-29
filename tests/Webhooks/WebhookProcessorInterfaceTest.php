<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
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

    public function testValidationFailureIsReturnedAsProcessingResult(): void
    {
        $processor = new class implements WebhookProcessorInterface {
            public function process(WebhookInput $input): WebhookProcessingResult
            {
                return WebhookProcessingResult::validationFailed();
            }
        };

        $result = $processor->process(new WebhookInput(rawBody: '{"id":"evt_invalid"}'));

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('validation_failed', $result->reason->code->value);
    }

}
