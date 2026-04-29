<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;

final class WebhookProviderValidatorInterfaceTest extends TestCase
{
    public function testProviderIdReturnTypeIsFixedToString(): void
    {
        $method = new ReflectionMethod(WebhookProviderValidatorInterface::class, 'getProviderId');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertFalse($returnType->allowsNull());
        $this->assertSame('string', $returnType->getName());
        $this->assertSame(0, $method->getNumberOfParameters());
    }

    public function testValidateSignatureAcceptsWebhookInputAndReturnsValidationResult(): void
    {
        $method = new ReflectionMethod(WebhookProviderValidatorInterface::class, 'validate');
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        $this->assertCount(1, $parameters);
        $this->assertSame('input', $parameters[0]->getName());
        $this->assertFalse($parameters[0]->allowsNull());
        $this->assertSame(WebhookInput::class, $parameters[0]->getType()?->getName());
        $this->assertNotNull($returnType);
        $this->assertFalse($returnType->allowsNull());
        $this->assertSame(WebhookValidationResult::class, $returnType->getName());
    }
}
