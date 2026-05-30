<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use Countable;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookStripeValidator;
use Yiisoft\Payments\Webhooks\WebhookPayPalValidator;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorRegistry;
use Yiisoft\Payments\Webhooks\WebhookCapabilities;
use Yiisoft\Payments\Webhooks\WebhookCapabilitiesProviderInterface;
use Yiisoft\Payments\Webhooks\WebhookCapability;
use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookEntityKind;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaValidator;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;
use Yiisoft\Payments\Webhooks\WebhookSupportStatus;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;
use Yiisoft\Payments\Webhooks\WebhookYooKassaValidator;

final class WebhookPublicContractTest extends TestCase
{
    public function testPaymentWebhookMapperInterfaceContractIsStable(): void
    {
        $reflection = new ReflectionClass(PaymentWebhookMapperInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertSame(['extractPaymentStatus', 'mapPaymentWebhook'], $this->methodNames($reflection, ReflectionMethod::IS_PUBLIC));

        $mapMethod = $reflection->getMethod('mapPaymentWebhook');

        $this->assertSame(1, $mapMethod->getNumberOfParameters());
        $this->assertSame('payload', $mapMethod->getParameters()[0]->getName());
        $this->assertSame(WebhookPayload::class, $mapMethod->getParameters()[0]->getType()?->getName());
        $this->assertFalse($mapMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame(WebhookProcessingResult::class, $mapMethod->getReturnType()?->getName());
        $this->assertFalse($mapMethod->getReturnType()?->allowsNull());

        $extractMethod = $reflection->getMethod('extractPaymentStatus');

        $this->assertSame(1, $extractMethod->getNumberOfParameters());
        $this->assertSame('payload', $extractMethod->getParameters()[0]->getName());
        $this->assertSame(WebhookPayload::class, $extractMethod->getParameters()[0]->getType()?->getName());
        $this->assertFalse($extractMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame('string', $extractMethod->getReturnType()?->getName());
        $this->assertTrue($extractMethod->getReturnType()?->allowsNull());
    }

    public function testWebhookProcessorContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookProcessor::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->implementsInterface(WebhookProcessorInterface::class));
        $this->assertSame(['__construct', 'process'], $this->methodNames($reflection, ReflectionMethod::IS_PUBLIC));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(2, $constructor->getNumberOfParameters());
        $this->assertSame('providerProcessorRegistry', $constructor->getParameters()[0]->getName());
        $this->assertSame(WebhookProviderProcessorRegistry::class, $constructor->getParameters()[0]->getType()?->getName());
        $this->assertFalse($constructor->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame('providerValidatorRegistry', $constructor->getParameters()[1]->getName());
        $this->assertSame(WebhookProviderValidatorRegistry::class, $constructor->getParameters()[1]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[1]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[1]->getDefaultValue());

        $processMethod = $reflection->getMethod('process');

        $this->assertSame(1, $processMethod->getNumberOfParameters());
        $this->assertSame(WebhookInput::class, $processMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookContext::class, $processMethod->getReturnType()?->getName());
        $this->assertFalse($processMethod->getReturnType()?->allowsNull());
    }

    public function testWebhookProcessorInterfaceContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookProcessorInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertSame(['process'], $this->methodNames($reflection, ReflectionMethod::IS_PUBLIC));

        $method = $reflection->getMethod('process');

        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame('input', $method->getParameters()[0]->getName());
        $this->assertSame(WebhookInput::class, $method->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookContext::class, $method->getReturnType()?->getName());
    }

    public function testWebhookProviderProcessorInterfaceContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookProviderProcessorInterface::class);

        $this->assertTrue($reflection->isInterface());
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


    public function testWebhookValidationResultContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookValidationResult::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertSame(['__construct', 'failure', 'success'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(['isValid', 'reason'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $constructor->getParameters(),
        ));
        $this->assertSame('bool', $constructor->getParameters()[0]->getType()?->getName());
        $this->assertFalse($constructor->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame(WebhookReason::class, $constructor->getParameters()[1]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[1]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[1]->getDefaultValue());

        $this->assertSame('bool', $reflection->getProperty('isValid')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('isValid')->isPublic());
        $this->assertTrue($reflection->getProperty('isValid')->isReadOnly());
        $this->assertSame(WebhookReason::class, $reflection->getProperty('reason')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('reason')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('reason')->isPublic());
        $this->assertTrue($reflection->getProperty('reason')->isReadOnly());

        $successMethod = $reflection->getMethod('success');

        $this->assertTrue($successMethod->isStatic());
        $this->assertSame(0, $successMethod->getNumberOfParameters());
        $this->assertSame('self', $successMethod->getReturnType()?->getName());
        $this->assertFalse($successMethod->getReturnType()?->allowsNull());

        $failureMethod = $reflection->getMethod('failure');

        $this->assertTrue($failureMethod->isStatic());
        $this->assertSame(1, $failureMethod->getNumberOfParameters());
        $this->assertSame('reason', $failureMethod->getParameters()[0]->getName());
        $this->assertSame(WebhookReason::class, $failureMethod->getParameters()[0]->getType()?->getName());
        $this->assertFalse($failureMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame('self', $failureMethod->getReturnType()?->getName());
        $this->assertFalse($failureMethod->getReturnType()?->allowsNull());
    }

    public function testWebhookProviderValidatorInterfaceContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookProviderValidatorInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertSame(['getProviderId', 'validate'], $this->methodNames($reflection, ReflectionMethod::IS_PUBLIC));

        $providerIdMethod = $reflection->getMethod('getProviderId');

        $this->assertSame(0, $providerIdMethod->getNumberOfParameters());
        $this->assertSame('string', $providerIdMethod->getReturnType()?->getName());
        $this->assertFalse($providerIdMethod->getReturnType()?->allowsNull());

        $validateMethod = $reflection->getMethod('validate');

        $this->assertSame(1, $validateMethod->getNumberOfParameters());
        $this->assertSame('input', $validateMethod->getParameters()[0]->getName());
        $this->assertSame(WebhookInput::class, $validateMethod->getParameters()[0]->getType()?->getName());
        $this->assertFalse($validateMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame(WebhookValidationResult::class, $validateMethod->getReturnType()?->getName());
        $this->assertFalse($validateMethod->getReturnType()?->allowsNull());
    }

    public function testWebhookStripeValidatorContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookStripeValidator::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->implementsInterface(WebhookProviderValidatorInterface::class));
        $this->assertSame(['__construct', 'getProviderId', 'validate'], $this->methodNames($reflection, ReflectionMethod::IS_PUBLIC));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(3, $constructor->getNumberOfParameters());
        $this->assertSame(1, $constructor->getNumberOfRequiredParameters());
        $this->assertSame('signingSecret', $constructor->getParameters()[0]->getName());
        $this->assertSame('string', $constructor->getParameters()[0]->getType()?->getName());
        $this->assertFalse($constructor->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame('timestampToleranceSeconds', $constructor->getParameters()[1]->getName());
        $this->assertSame('int', $constructor->getParameters()[1]->getType()?->getName());
        $this->assertFalse($constructor->getParameters()[1]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[1]->isDefaultValueAvailable());
        $this->assertSame('currentTimestamp', $constructor->getParameters()[2]->getName());
        $this->assertSame('int', $constructor->getParameters()[2]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[2]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[2]->isDefaultValueAvailable());

        $providerIdMethod = $reflection->getMethod('getProviderId');

        $this->assertSame(0, $providerIdMethod->getNumberOfParameters());
        $this->assertSame('string', $providerIdMethod->getReturnType()?->getName());
        $this->assertFalse($providerIdMethod->getReturnType()?->allowsNull());

        $validateMethod = $reflection->getMethod('validate');

        $this->assertSame(1, $validateMethod->getNumberOfParameters());
        $this->assertSame('input', $validateMethod->getParameters()[0]->getName());
        $this->assertSame(WebhookInput::class, $validateMethod->getParameters()[0]->getType()?->getName());
        $this->assertFalse($validateMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame(WebhookValidationResult::class, $validateMethod->getReturnType()?->getName());
        $this->assertFalse($validateMethod->getReturnType()?->allowsNull());
    }

    public function testWebhookPayPalValidatorContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookPayPalValidator::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->implementsInterface(WebhookProviderValidatorInterface::class));
        $this->assertSame(['__construct', 'getProviderId', 'validate'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertSame('webhookId', $constructor->getParameters()[0]->getName());
        $this->assertSame('string', $constructor->getParameters()[0]->getType()?->getName());
        $this->assertFalse($constructor->getParameters()[0]->getType()?->allowsNull());

        $providerIdMethod = $reflection->getMethod('getProviderId');

        $this->assertSame(0, $providerIdMethod->getNumberOfParameters());
        $this->assertSame('string', $providerIdMethod->getReturnType()?->getName());
        $this->assertFalse($providerIdMethod->getReturnType()?->allowsNull());

        $validateMethod = $reflection->getMethod('validate');

        $this->assertSame(1, $validateMethod->getNumberOfParameters());
        $this->assertSame('input', $validateMethod->getParameters()[0]->getName());
        $this->assertSame(WebhookInput::class, $validateMethod->getParameters()[0]->getType()?->getName());
        $this->assertFalse($validateMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame(WebhookValidationResult::class, $validateMethod->getReturnType()?->getName());
        $this->assertFalse($validateMethod->getReturnType()?->allowsNull());
    }

    public function testWebhookRobokassaCallbackFormatContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookRobokassaCallbackFormat::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertSame(['__construct', 'isCustomParameter', 'isRequiredParameter', 'requiredParameters'], $this->methodNames($reflection));
        $this->assertTrue($reflection->getConstructor()?->isPrivate());

        $this->assertSame('robokassa', $reflection->getConstant('PROVIDER_ID'));
        $this->assertSame('result_url', $reflection->getConstant('CALLBACK_TYPE'));
        $this->assertSame('SignatureValue', $reflection->getConstant('SIGNATURE_PARAMETER'));
        $this->assertSame('password2', $reflection->getConstant('SIGNATURE_SECRET'));
        $this->assertSame('md5', $reflection->getConstant('SIGNATURE_ALGORITHM'));
        $this->assertSame('Shp_', $reflection->getConstant('CUSTOM_PARAMETER_PREFIX'));

        $requiredParametersMethod = $reflection->getMethod('requiredParameters');

        $this->assertTrue($requiredParametersMethod->isStatic());
        $this->assertSame(0, $requiredParametersMethod->getNumberOfParameters());
        $this->assertSame('array', $requiredParametersMethod->getReturnType()?->getName());
        $this->assertFalse($requiredParametersMethod->getReturnType()?->allowsNull());

        $isRequiredParameterMethod = $reflection->getMethod('isRequiredParameter');

        $this->assertTrue($isRequiredParameterMethod->isStatic());
        $this->assertSame(1, $isRequiredParameterMethod->getNumberOfParameters());
        $this->assertSame('name', $isRequiredParameterMethod->getParameters()[0]->getName());
        $this->assertSame('string', $isRequiredParameterMethod->getParameters()[0]->getType()?->getName());
        $this->assertFalse($isRequiredParameterMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame('bool', $isRequiredParameterMethod->getReturnType()?->getName());
        $this->assertFalse($isRequiredParameterMethod->getReturnType()?->allowsNull());

        $isCustomParameterMethod = $reflection->getMethod('isCustomParameter');

        $this->assertTrue($isCustomParameterMethod->isStatic());
        $this->assertSame(1, $isCustomParameterMethod->getNumberOfParameters());
        $this->assertSame('name', $isCustomParameterMethod->getParameters()[0]->getName());
        $this->assertSame('string', $isCustomParameterMethod->getParameters()[0]->getType()?->getName());
        $this->assertFalse($isCustomParameterMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame('bool', $isCustomParameterMethod->getReturnType()?->getName());
        $this->assertFalse($isCustomParameterMethod->getReturnType()?->allowsNull());
    }

    public function testWebhookRobokassaValidatorContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookRobokassaValidator::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->implementsInterface(WebhookProviderValidatorInterface::class));
        $this->assertSame(['__construct', 'getProviderId', 'validate'], $this->methodNames($reflection, ReflectionMethod::IS_PUBLIC));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertSame('password2', $constructor->getParameters()[0]->getName());
        $this->assertSame('string', $constructor->getParameters()[0]->getType()?->getName());
        $this->assertFalse($constructor->getParameters()[0]->getType()?->allowsNull());

        $providerIdMethod = $reflection->getMethod('getProviderId');

        $this->assertSame(0, $providerIdMethod->getNumberOfParameters());
        $this->assertSame('string', $providerIdMethod->getReturnType()?->getName());
        $this->assertFalse($providerIdMethod->getReturnType()?->allowsNull());

        $validateMethod = $reflection->getMethod('validate');

        $this->assertSame(1, $validateMethod->getNumberOfParameters());
        $this->assertSame('input', $validateMethod->getParameters()[0]->getName());
        $this->assertSame(WebhookInput::class, $validateMethod->getParameters()[0]->getType()?->getName());
        $this->assertFalse($validateMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame(WebhookValidationResult::class, $validateMethod->getReturnType()?->getName());
        $this->assertFalse($validateMethod->getReturnType()?->allowsNull());
    }

    public function testWebhookYooKassaValidatorContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookYooKassaValidator::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->implementsInterface(WebhookProviderValidatorInterface::class));
        $this->assertSame(['getProviderId', 'validate'], $this->methodNames($reflection, ReflectionMethod::IS_PUBLIC));
        $this->assertNull($reflection->getConstructor());

        $providerIdMethod = $reflection->getMethod('getProviderId');

        $this->assertSame(0, $providerIdMethod->getNumberOfParameters());
        $this->assertSame('string', $providerIdMethod->getReturnType()?->getName());
        $this->assertFalse($providerIdMethod->getReturnType()?->allowsNull());

        $validateMethod = $reflection->getMethod('validate');

        $this->assertSame(1, $validateMethod->getNumberOfParameters());
        $this->assertSame('input', $validateMethod->getParameters()[0]->getName());
        $this->assertSame(WebhookInput::class, $validateMethod->getParameters()[0]->getType()?->getName());
        $this->assertFalse($validateMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertSame(WebhookValidationResult::class, $validateMethod->getReturnType()?->getName());
        $this->assertFalse($validateMethod->getReturnType()?->allowsNull());
    }

    public function testWebhookProviderValidatorRegistryContractIsStable(): void
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

        $this->assertSame(['providerId'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $getMethod->getParameters(),
        ));
        $this->assertSame('string', $getMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookProviderValidatorInterface::class, $getMethod->getReturnType()?->getName());
        $this->assertTrue($getMethod->getReturnType()?->allowsNull());

        $hasMethod = $reflection->getMethod('has');

        $this->assertSame(['providerId'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $hasMethod->getParameters(),
        ));
        $this->assertSame('string', $hasMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame('bool', $hasMethod->getReturnType()?->getName());
        $this->assertFalse($hasMethod->getReturnType()?->allowsNull());
    }

    public function testWebhookProviderProcessorRegistryContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookProviderProcessorRegistry::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertSame(['__construct', 'get', 'has', 'missingProcessorResult'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertTrue($constructor->getParameters()[0]->isVariadic());
        $this->assertSame(WebhookProviderProcessorInterface::class, $constructor->getParameters()[0]->getType()?->getName());

        $getMethod = $reflection->getMethod('get');

        $this->assertSame(['providerId'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $getMethod->getParameters(),
        ));
        $this->assertSame('string', $getMethod->getParameters()[0]->getType()?->getName());
        $this->assertSame(WebhookProviderProcessorInterface::class, $getMethod->getReturnType()?->getName());
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

    public function testWebhookContextContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookContext::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertSame(['__construct'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(['providerId', 'eventType', 'status', 'paymentStatus', 'validationFailureReason', 'unsupportedEventReason', 'unknownEventReason', 'rawInput', 'rawData'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $constructor->getParameters(),
        ));
        $this->assertSame('string', $constructor->getParameters()[0]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[0]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[0]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[0]->getDefaultValue());
        $this->assertSame(WebhookEventType::class, $constructor->getParameters()[1]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[1]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[1]->getDefaultValue());
        $this->assertSame(WebhookProcessingStatus::class, $constructor->getParameters()[2]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[2]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[2]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[2]->getDefaultValue());
        $this->assertSame('string', $constructor->getParameters()[3]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[3]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[3]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[3]->getDefaultValue());
        $this->assertSame(WebhookReason::class, $constructor->getParameters()[4]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[4]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[4]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[4]->getDefaultValue());
        $this->assertSame(WebhookReason::class, $constructor->getParameters()[5]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[5]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[5]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[5]->getDefaultValue());
        $this->assertSame(WebhookReason::class, $constructor->getParameters()[6]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[6]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[6]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[6]->getDefaultValue());
        $this->assertSame(WebhookInput::class, $constructor->getParameters()[7]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[7]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[7]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[7]->getDefaultValue());
        $this->assertSame(WebhookRawData::class, $constructor->getParameters()[8]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[8]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[8]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[8]->getDefaultValue());

        $this->assertSame('string', $reflection->getProperty('providerId')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('providerId')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('providerId')->isPublic());
        $this->assertTrue($reflection->getProperty('providerId')->isReadOnly());
        $this->assertSame(WebhookEventType::class, $reflection->getProperty('eventType')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('eventType')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('eventType')->isPublic());
        $this->assertTrue($reflection->getProperty('eventType')->isReadOnly());
        $this->assertSame(WebhookProcessingStatus::class, $reflection->getProperty('status')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('status')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('status')->isPublic());
        $this->assertTrue($reflection->getProperty('status')->isReadOnly());
        $this->assertSame('string', $reflection->getProperty('paymentStatus')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('paymentStatus')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('paymentStatus')->isPublic());
        $this->assertTrue($reflection->getProperty('paymentStatus')->isReadOnly());
        $this->assertSame(WebhookReason::class, $reflection->getProperty('validationFailureReason')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('validationFailureReason')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('validationFailureReason')->isPublic());
        $this->assertTrue($reflection->getProperty('validationFailureReason')->isReadOnly());
        $this->assertSame(WebhookReason::class, $reflection->getProperty('unsupportedEventReason')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('unsupportedEventReason')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('unsupportedEventReason')->isPublic());
        $this->assertTrue($reflection->getProperty('unsupportedEventReason')->isReadOnly());
        $this->assertSame(WebhookReason::class, $reflection->getProperty('unknownEventReason')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('unknownEventReason')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('unknownEventReason')->isPublic());
        $this->assertTrue($reflection->getProperty('unknownEventReason')->isReadOnly());
        $this->assertSame(WebhookInput::class, $reflection->getProperty('rawInput')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('rawInput')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('rawInput')->isPublic());
        $this->assertTrue($reflection->getProperty('rawInput')->isReadOnly());
        $this->assertSame(WebhookRawData::class, $reflection->getProperty('rawData')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('rawData')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('rawData')->isPublic());
        $this->assertTrue($reflection->getProperty('rawData')->isReadOnly());
    }

    public function testWebhookRawDataContractIsStable(): void
    {
        $reflection = new ReflectionClass(WebhookRawData::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertSame(['__construct', 'getBodyParams', 'getHeaders', 'getPayload', 'getProviderEventType', 'getQueryParams'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(['rawBody', 'headers', 'payload', 'providerEventType', 'queryParams', 'bodyParams'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $constructor->getParameters(),
        ));
        $this->assertSame('string', $constructor->getParameters()[0]->getType()?->getName());
        $this->assertSame('array', $constructor->getParameters()[1]->getType()?->getName());
        $this->assertSame('mixed', $constructor->getParameters()[2]->getType()?->getName());
        $this->assertSame('string', $constructor->getParameters()[3]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[3]->getType()?->allowsNull());
        $this->assertSame('array', $constructor->getParameters()[4]->getType()?->getName());
        $this->assertSame('array', $constructor->getParameters()[5]->getType()?->getName());

        foreach ([1, 2, 3, 4, 5] as $index) {
            $this->assertTrue($constructor->getParameters()[$index]->isDefaultValueAvailable());
        }

        foreach (['rawBody', 'headers', 'queryParams', 'bodyParams', 'payload', 'providerEventType'] as $propertyName) {
            $this->assertTrue($reflection->getProperty($propertyName)->isPublic());
            $this->assertTrue($reflection->getProperty($propertyName)->isReadOnly());
        }

        $this->assertSame('array', $reflection->getProperty('queryParams')->getType()?->getName());
        $this->assertSame('array', $reflection->getProperty('bodyParams')->getType()?->getName());
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
        $this->assertSame(['__construct', 'missingProviderProcessor', 'processed', 'unknownEvent', 'unsupportedEvent', 'validationFailed'], $this->methodNames($reflection));

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(['status', 'eventType', 'reason', 'rawData', 'paymentStatus'], array_map(
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
        $this->assertSame('string', $constructor->getParameters()[4]->getType()?->getName());
        $this->assertTrue($constructor->getParameters()[4]->getType()?->allowsNull());
        $this->assertTrue($constructor->getParameters()[4]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[4]->getDefaultValue());

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
        $this->assertSame('string', $reflection->getProperty('paymentStatus')->getType()?->getName());
        $this->assertTrue($reflection->getProperty('paymentStatus')->getType()?->allowsNull());
        $this->assertTrue($reflection->getProperty('paymentStatus')->isPublic());
        $this->assertTrue($reflection->getProperty('paymentStatus')->isReadOnly());
        $validationFailedMethod = $reflection->getMethod('validationFailed');

        $this->assertSame(['rawData', 'reason'], array_map(
            static fn ($parameter): string => $parameter->getName(),
            $validationFailedMethod->getParameters(),
        ));
        $this->assertSame(WebhookRawData::class, $validationFailedMethod->getParameters()[0]->getType()?->getName());
        $this->assertTrue($validationFailedMethod->getParameters()[0]->getType()?->allowsNull());
        $this->assertTrue($validationFailedMethod->getParameters()[0]->isDefaultValueAvailable());
        $this->assertNull($validationFailedMethod->getParameters()[0]->getDefaultValue());
        $this->assertSame(WebhookReason::class, $validationFailedMethod->getParameters()[1]->getType()?->getName());
        $this->assertTrue($validationFailedMethod->getParameters()[1]->getType()?->allowsNull());
        $this->assertTrue($validationFailedMethod->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($validationFailedMethod->getParameters()[1]->getDefaultValue());
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
