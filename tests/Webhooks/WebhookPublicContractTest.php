<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use Countable;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\ProviderWebhookProcessorInterface;
use Yiisoft\Payments\Webhooks\ProviderWebhookProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookCapabilities;
use Yiisoft\Payments\Webhooks\WebhookCapabilitiesProviderInterface;
use Yiisoft\Payments\Webhooks\WebhookCapability;
use Yiisoft\Payments\Webhooks\WebhookEntityKind;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;
use Yiisoft\Payments\Webhooks\WebhookSupportStatus;

final class WebhookPublicContractTest extends TestCase
{
    public function testWebhookProcessorInterfaceContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookProcessorInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertSame(['process'], $this->methodNames($reflection, ReflectionMethod::IS_PUBLIC));

        $method = $reflection->getMethod('process');

        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame('input', $method->getParameters()[0]->getName());
        $this->assertSame(WebhookInput::class, $method->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookProcessingResult::class, $method->getReturnType()?->getName());
    }

    public function testProviderWebhookProcessorInterfaceContractIsStable(): void
    {
        $reflection = new ReflectionClass(ProviderWebhookProcessorInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->implementsInterface(WebhookProcessorInterface::class));
        $this->assertSame(['getProviderId', 'process'], $this->methodNames($reflection, ReflectionMethod::IS_PUBLIC));

        $providerIdMethod = $reflection->getMethod('getProviderId');

        $this->assertSame(0, $providerIdMethod->getNumberOfParameters());
        $this->assertSame('string', $providerIdMethod->getReturnType()?->getName());
        $this->assertFalse($providerIdMethod->getReturnType()?->allowsNull());

        $processMethod = $reflection->getMethod('process');

        $this->assertSame(1, $processMethod->getNumberOfParameters());
        $this->assertSame(WebhookInput::class, $processMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookProcessingResult::class, $processMethod->getReturnType()?->getName());
    }


    public function testProviderWebhookProcessorRegistryContractIsStable(): void
    {
        $reflection = new ReflectionClass(ProviderWebhookProcessorRegistry::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertSame(['__construct', 'get', 'missingProcessorResult', 'has'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertTrue($constructor->getParameters()[0]->isVariadic());
        $this->assertSame(ProviderWebhookProcessorInterface::class, $constructor->getParameters()[0]->getType()?->getName());

        $getMethod = $reflection->getMethod('get');

        $this->assertSame(['providerId'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $getMethod->getParameters(),
        ));
        $this->assertSame('string', $getMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame(ProviderWebhookProcessorInterface::class, $getMethod->getReturnType()?->getName());
        $this->assertTrue($getMethod->getReturnType()?->allowsNull());

        $missingProcessorResultMethod = $reflection->getMethod('missingProcessorResult');

        $this->assertSame(['providerId', 'rawData'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $missingProcessorResultMethod->getParameters(),
        ));
        $this->assertSame('string', $missingProcessorResultMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookRawData::class, $missingProcessorResultMethod->getParameters()[1]->getType()?->getName());
        $this->assertTrue($missingProcessorResultMethod->getParameters()[1]->getType()?->allowsNull());
        $this->assertTrue($missingProcessorResultMethod->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($missingProcessorResultMethod->getParameters()[1]->getDefaultValue());
        $this->assertSame(WebhookProcessingResult::class, $missingProcessorResultMethod->getReturnType()?->getName());
        $this->assertFalse($missingProcessorResultMethod->getReturnType()?->allowsNull());

        $hasMethod = $reflection->getMethod('has');

        $this->assertSame(['providerId'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $hasMethod->getParameters(),
        ));
        $this->assertSame('string', $hasMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame('bool', $hasMethod->getReturnType()?->getName());
        $this->assertFalse($hasMethod->getReturnType()?->allowsNull());
    }

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

    public function testWebhookProcessingResultContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookProcessingResult::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertSame(['__construct', 'validationFailed', 'missingProviderProcessor', 'unknownEvent', 'unsupportedEvent'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(['status', 'eventType', 'reason', 'rawData'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $constructor->getParameters(),
        ));
        $this->assertSame(WebhookProcessingStatus::class, $constructor->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookEventType::class, $constructor->getParameters()[1]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[1]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[1]->getDefaultValue());
        $this->assertSame(WebhookReason::class, $constructor->getParameters()[2]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[2]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[2]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[2]->getDefaultValue());
        $this->assertSame(WebhookRawData::class, $constructor->getParameters()[3]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[3]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[3]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[3]->getDefaultValue());

        $this->assertSame(WebhookProcessingStatus::class, $reflection->getProperty('status')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('status')->isPublic());
        $this->assertTrue($reflection->getProperty('status')->isReadOnly());
        $this->assertSame(WebhookEventType::class, $reflection->getProperty('eventType')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('eventType')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('eventType')->isPublic());
        $this->assertTrue($reflection->getProperty('eventType')->isReadOnly());
        $this->assertSame(WebhookReason::class, $reflection->getProperty('reason')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('reason')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('reason')->isPublic());
        $this->assertTrue($reflection->getProperty('reason')->isReadOnly());
        $this->assertSame(WebhookRawData::class, $reflection->getProperty('rawData')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('rawData')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('rawData')->isPublic());
        $this->assertTrue($reflection->getProperty('rawData')->isReadOnly());
        $validationFailedMethod = $reflection->getMethod('validationFailed');

        $this->assertSame(['rawData'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $validationFailedMethod->getParameters(),
        ));
        $this->assertSame(WebhookRawData::class, $validationFailedMethod->getParameters()[0]->getType()?->getName());
        $this->assertTrue($validationFailedMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertTrue($validationFailedMethod->getParameters()[0]->isDefaultValueAvailable());
        $this->assertNull($validationFailedMethod->getParameters()[0]->getDefaultValue());
        $this->assertSame('self', $validationFailedMethod->getReturnType()?->getName());

        $missingProviderProcessorMethod = $reflection->getMethod('missingProviderProcessor');

        $this->assertSame(['providerId', 'rawData'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $missingProviderProcessorMethod->getParameters(),
        ));
        $this->assertSame('string', $missingProviderProcessorMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookRawData::class, $missingProviderProcessorMethod->getParameters()[1]->getType()?->getName());
        $this->assertTrue($missingProviderProcessorMethod->getParameters()[1]->getType()?->allowsNull());
        $this->assertTrue($missingProviderProcessorMethod->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($missingProviderProcessorMethod->getParameters()[1]->getDefaultValue());
        $this->assertSame('self', $missingProviderProcessorMethod->getReturnType()?->getName());

        $unknownEventMethod = $reflection->getMethod('unknownEvent');

        $this->assertSame(['providerEventType'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $unknownEventMethod->getParameters(),
        ));
        $this->assertSame('string', $unknownEventMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame('self', $unknownEventMethod->getReturnType()?->getName());

        $unsupportedEventMethod = $reflection->getMethod('unsupportedEvent');

        $this->assertSame(['eventType', 'providerEventType', 'rawData'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $unsupportedEventMethod->getParameters(),
        ));
        $this->assertSame(WebhookEventType::class, $unsupportedEventMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame('string', $unsupportedEventMethod->getParameters()[1]->getType()?->getName());
        $this->assertTrue($unsupportedEventMethod->getParameters()[1]->getType()?->allowsNull());
        $this->assertTrue($unsupportedEventMethod->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($unsupportedEventMethod->getParameters()[1]->getDefaultValue());
        $this->assertSame(WebhookRawData::class, $unsupportedEventMethod->getParameters()[2]->getType()?->getName());
        $this->assertTrue($unsupportedEventMethod->getParameters()[2]->getType()?->allowsNull());
        $this->assertTrue($unsupportedEventMethod->getParameters()[2]->isDefaultValueAvailable());
        $this->assertNull($unsupportedEventMethod->getParameters()[2]->getDefaultValue());
        $this->assertSame('self', $unsupportedEventMethod->getReturnType()?->getName());
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

    public function testWebhookReasonContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookReason::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertSame(['__construct'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(['code', 'message', 'providerEventType'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $constructor->getParameters(),
        ));
        $this->assertSame(WebhookReasonCode::class, $constructor->getParameters()[0]->getType()?->getName());
        $this->assertSame('string', $constructor->getParameters()[1]->getType()?->getName());
        $this->assertSame('string', $constructor->getParameters()[2]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[2]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[2]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[2]->getDefaultValue());

        $this->assertSame(WebhookReasonCode::class, $reflection->getProperty('code')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('code')->isPublic());
        $this->assertTrue($reflection->getProperty('code')->isReadOnly());
        $this->assertSame('string', $reflection->getProperty('message')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('message')->isPublic());
        $this->assertTrue($reflection->getProperty('message')->isReadOnly());
        $this->assertSame('string', $reflection->getProperty('providerEventType')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('providerEventType')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('providerEventType')->isPublic());
        $this->assertTrue($reflection->getProperty('providerEventType')->isReadOnly());
    }

    public function testWebhookCapabilitiesCollectionContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookCapabilities::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->implementsInterface(Countable::class));
        $this->assertTrue($reflection->implementsInterface(IteratorAggregate::class));
        $this->assertSame(['__construct', 'all', 'count', 'getIterator', 'unsupportedResultFor'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertTrue($constructor->getParameters()[0]->isVariadic());
        $this->assertSame(WebhookCapability::class, $constructor->getParameters()[0]->getType()?->getName());

        $unsupportedResultForMethod = $reflection->getMethod('unsupportedResultFor');

        $this->assertSame(['eventType', 'entityKind', 'providerEventType', 'rawData'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $unsupportedResultForMethod->getParameters(),
        ));
        $this->assertSame(WebhookEventType::class, $unsupportedResultForMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookEntityKind::class, $unsupportedResultForMethod->getParameters()[1]->getType()?->getName());
        $this->assertSame('string', $unsupportedResultForMethod->getParameters()[2]->getType()?->getName());
        $this->assertTrue($unsupportedResultForMethod->getParameters()[2]->getType()?->allowsNull());
        $this->assertTrue($unsupportedResultForMethod->getParameters()[2]->isDefaultValueAvailable());
        $this->assertNull($unsupportedResultForMethod->getParameters()[2]->getDefaultValue());
        $this->assertSame(WebhookRawData::class, $unsupportedResultForMethod->getParameters()[3]->getType()?->getName());
        $this->assertTrue($unsupportedResultForMethod->getParameters()[3]->getType()?->allowsNull());
        $this->assertTrue($unsupportedResultForMethod->getParameters()[3]->isDefaultValueAvailable());
        $this->assertNull($unsupportedResultForMethod->getParameters()[3]->getDefaultValue());
        $this->assertSame(WebhookProcessingResult::class, $unsupportedResultForMethod->getReturnType()?->getName());
        $this->assertTrue($unsupportedResultForMethod->getReturnType()?->allowsNull());

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
