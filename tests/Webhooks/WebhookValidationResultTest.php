<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;

final class WebhookValidationResultTest extends TestCase
{
    public function testStoresSuccessfulValidationState(): void
    {
        $result = new WebhookValidationResult(isValid: true);

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testStoresFailedValidationStateAndReason(): void
    {
        $reason = $this->reason();
        $result = new WebhookValidationResult(isValid: false, reason: $reason);

        $this->assertFalse($result->isValid);
        $this->assertSame($reason, $result->reason);
    }

    public function testSuccessfulValidationResultMustNotContainFailureReason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Successful webhook validation result must not contain a failure reason.');

        new WebhookValidationResult(isValid: true, reason: $this->reason());
    }

    public function testFailedValidationResultMustContainFailureReason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed webhook validation result must contain a failure reason.');

        new WebhookValidationResult(isValid: false);
    }

    public function testFailedValidationResultCarriesSingleFailureReason(): void
    {
        $reason = $this->reason();
        $result = WebhookValidationResult::failure($reason);
        $reflection = new ReflectionClass($result);

        $this->assertSame($reason, $result->reason);
        $this->assertFalse($reflection->hasProperty('errors'));
        $this->assertFalse($reflection->hasProperty('reasons'));
    }

    public function testCreatesSuccessfulValidationResult(): void
    {
        $result = WebhookValidationResult::success();

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testCreatesFailedValidationResult(): void
    {
        $reason = $this->reason();
        $result = WebhookValidationResult::failure($reason);

        $this->assertFalse($result->isValid);
        $this->assertSame($reason, $result->reason);
    }

    public function testResultIsFinalReadonlyValueObject(): void
    {
        $reflection = new ReflectionClass(WebhookValidationResult::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->getProperty('isValid')->isPublic());
        $this->assertTrue($reflection->getProperty('isValid')->isReadOnly());
        $this->assertTrue($reflection->getProperty('reason')->isPublic());
        $this->assertTrue($reflection->getProperty('reason')->isReadOnly());
    }

    public function testValidationStateCannotBeChangedAfterCreation(): void
    {
        $result = new WebhookValidationResult(isValid: true);

        $this->expectException(Error::class);

        $result->isValid = false;
    }

    public function testValidationReasonCannotBeChangedAfterCreation(): void
    {
        $result = new WebhookValidationResult(isValid: false, reason: $this->reason());

        $this->expectException(Error::class);

        $result->reason = $this->reason('different_signature');
    }

    private function reason(string $code = 'invalid_signature'): WebhookReason
    {
        return new WebhookReason(
            code: new WebhookReasonCode($code),
            message: 'Webhook signature is invalid.',
        );
    }
}
