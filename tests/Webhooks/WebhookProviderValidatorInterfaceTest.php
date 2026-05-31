<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;
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

    public function testValidatorCanStopAtFirstDecisiveValidationFailure(): void
    {
        $validator = new WebhookFailFastProviderValidator();
        $input = new WebhookInput(
            rawBody: '{"id":"evt_1"}',
            headers: [],
            providerId: 'test-provider',
        );

        $result = $validator->validate($input);

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('missing_signature', $result->reason->code->value);
        $this->assertSame(1, $validator->executedChecks);
    }

    public function testValidatorReturnsSuccessOnlyAfterAllChecksPass(): void
    {
        $validator = new WebhookFailFastProviderValidator();
        $input = new WebhookInput(
            rawBody: '{"id":"evt_1"}',
            headers: [
                'X-Test-Signature' => 'valid-signature',
                'X-Test-Secret' => 'valid-secret',
            ],
            providerId: 'test-provider',
        );

        $result = $validator->validate($input);

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
        $this->assertSame(2, $validator->executedChecks);
    }
}

final class WebhookFailFastProviderValidator implements WebhookProviderValidatorInterface
{
    public int $executedChecks = 0;

    public function getProviderId(): string
    {
        return 'test-provider';
    }

    public function validate(WebhookInput $input): WebhookValidationResult
    {
        ++$this->executedChecks;

        if ($input->getHeader('X-Test-Signature') === []) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('missing_signature'),
                message: 'Webhook signature header is missing.',
            ));
        }

        ++$this->executedChecks;

        if ($input->getHeader('X-Test-Secret') === []) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('missing_secret'),
                message: 'Webhook secret header is missing.',
            ));
        }

        return WebhookValidationResult::success();
    }
}
