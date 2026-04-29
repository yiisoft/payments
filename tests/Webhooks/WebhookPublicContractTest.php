<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use Countable;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookCapabilities;
use Yiisoft\Payments\Webhooks\WebhookCapabilitiesProviderInterface;
use Yiisoft\Payments\Webhooks\WebhookCapability;
use Yiisoft\Payments\Webhooks\WebhookEntityKind;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;
use Yiisoft\Payments\Webhooks\WebhookSupportStatus;

final class WebhookPublicContractTest extends TestCase
{
    public function testWebhookCapabilitiesProviderInterfaceContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookCapabilitiesProviderInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertSame(['getWebhookCapabilities'], $this->methodNames($reflection, ReflectionMethod::IS_PUBLIC));

        $method = $reflection->getMethod('getWebhookCapabilities');

        $this->assertSame(0, $method->getNumberOfParameters());
        $this->assertSame(WebhookCapabilities::class, $method->getReturnType()?->getName());
    }

    public function testWebhookCapabilityModelContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookCapability::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertSame(['__construct'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(['eventType', 'entityKind', 'supportStatus'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $constructor->getParameters(),
        ));
        $this->assertSame(WebhookEventType::class, $constructor->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookEntityKind::class, $constructor->getParameters()[1]->getType()?->getName());
        $this->assertSame(WebhookSupportStatus::class, $constructor->getParameters()[2]->getType()?->getName());

        $this->assertSame(WebhookEventType::class, $reflection->getProperty('eventType')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('eventType')->isPublic());
        $this->assertTrue($reflection->getProperty('eventType')->isReadOnly());
        $this->assertSame(WebhookEntityKind::class, $reflection->getProperty('entityKind')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('entityKind')->isPublic());
        $this->assertTrue($reflection->getProperty('entityKind')->isReadOnly());
        $this->assertSame(WebhookSupportStatus::class, $reflection->getProperty('supportStatus')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('supportStatus')->isPublic());
        $this->assertTrue($reflection->getProperty('supportStatus')->isReadOnly());
    }

    public function testWebhookProcessingStatusContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookProcessingStatus::class);

        $this->assertTrue($reflection->isEnum());
        $this->assertTrue($reflection->isFinal());
        $this->assertSame(['Processed', 'ValidationFailed', 'UnknownEvent', 'UnsupportedEvent'], array_map(
            static fn (WebhookProcessingStatus $status): string => $status->name,
            WebhookProcessingStatus::cases(),
        ));
    }

    public function testWebhookReasonCodeContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookReasonCode::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertSame(['__construct', '__toString'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(['value'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $constructor->getParameters(),
        ));
        $this->assertSame('string', $constructor->getParameters()[0]->getType()?->getName());

        $this->assertSame('string', $reflection->getProperty('value')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('value')->isPublic());
        $this->assertTrue($reflection->getProperty('value')->isReadOnly());
        $this->assertSame('string', $reflection->getMethod('__toString')->getReturnType()?->getName());
    }

    public function testWebhookCapabilitiesCollectionContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookCapabilities::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->implementsInterface(Countable::class));
        $this->assertTrue($reflection->implementsInterface(IteratorAggregate::class));
        $this->assertSame(['__construct', 'all', 'count', 'getIterator'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertTrue($constructor->getParameters()[0]->isVariadic());
        $this->assertSame(WebhookCapability::class, $constructor->getParameters()[0]->getType()?->getName());

        $this->assertSame('array', $reflection->getMethod('all')->getReturnType()?->getName());
        $this->assertSame('int', $reflection->getMethod('count')->getReturnType()?->getName());
        $this->assertSame('Traversable', $reflection->getMethod('getIterator')->getReturnType()?->getName());
    }

    /**
     * @return list<string>
     */
    private function methodNames(ReflectionClass $reflection, ?int $filter = null): array
    {
        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $filter === null ? $reflection->getMethods() : $reflection->getMethods($filter),
        );

        sort($methods);

        return $methods;
    }
}
