<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Yiisoft\Payments\Tests\Webhooks\Support\WebhookSuccessfulProviderValidator;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorRegistry;

final class WebhookProviderValidatorRegistryTest extends TestCase
{
    public function testRegistryIsProviderValidatorResolver(): void
    {
        $reflection = new ReflectionClass(WebhookProviderValidatorRegistry::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertSame(['__construct', 'get', 'has'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertTrue($constructor->getParameters()[0]->isVariadic());
        $this->assertSame(WebhookProviderValidatorInterface::class, $constructor->getParameters()[0]->getType()?->getName());

        $getMethod = $reflection->getMethod('get');

        $this->assertSame(['providerId'], $this->parameterNames($getMethod));
        $this->assertSame('string', $getMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookProviderValidatorInterface::class, $getMethod->getReturnType()?->getName());
        $this->assertTrue($getMethod->getReturnType()?->allowsNull());
    }

    public function testRegistryReturnsValidatorByProviderId(): void
    {
        $stripeValidator = new WebhookSuccessfulProviderValidator('stripe');
        $paypalValidator = new WebhookSuccessfulProviderValidator('paypal');
        $registry = new WebhookProviderValidatorRegistry($stripeValidator, $paypalValidator);

        $this->assertSame($stripeValidator, $registry->get('stripe'));
        $this->assertSame($paypalValidator, $registry->get('paypal'));
        $this->assertTrue($registry->has('stripe'));
        $this->assertTrue($registry->has('paypal'));
        $this->assertFalse($registry->has('robokassa'));
        $this->assertNull($registry->get('robokassa'));
    }

    public function testRegistryRejectsEmptyProviderValidatorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook provider validator ID must be a non-empty string.');

        new WebhookProviderValidatorRegistry(new WebhookSuccessfulProviderValidator('  '));
    }

    public function testRegistryRejectsDuplicateProviderValidatorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook provider validator with ID "stripe" is already registered.');

        new WebhookProviderValidatorRegistry(
            new WebhookSuccessfulProviderValidator('stripe'),
            new WebhookSuccessfulProviderValidator('stripe'),
        );
    }

    /**
     * @return list<string>
     */
    private function methodNames(ReflectionClass $reflection): array
    {
        $methods = array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->class === $reflection->getName(),
        );

        return array_values(array_map(static fn (ReflectionMethod $method): string => $method->getName(), $methods));
    }

    /**
     * @return list<string>
     */
    private function parameterNames(ReflectionMethod $method): array
    {
        return array_map(static fn ($parameter): string => $parameter->getName(), $method->getParameters());
    }
}
