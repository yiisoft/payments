<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use Error;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;

final class WebhookValidationResultTest extends TestCase
{
    public function testStoresSuccessfulValidationState(): void
    {
        $result = new WebhookValidationResult(isValid: true);

        $this->assertTrue($result->isValid);
    }

    public function testStoresFailedValidationState(): void
    {
        $result = new WebhookValidationResult(isValid: false);

        $this->assertFalse($result->isValid);
    }

    public function testCreatesSuccessfulValidationResult(): void
    {
        $result = WebhookValidationResult::success();

        $this->assertTrue($result->isValid);
    }

    public function testCreatesFailedValidationResult(): void
    {
        $result = WebhookValidationResult::failure();

        $this->assertFalse($result->isValid);
    }

    public function testResultIsFinalReadonlyValueObject(): void
    {
        $reflection = new ReflectionClass(WebhookValidationResult::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->getProperty('isValid')->isPublic());
        $this->assertTrue($reflection->getProperty('isValid')->isReadOnly());
    }

    public function testValidationStateCannotBeChangedAfterCreation(): void
    {
        $result = new WebhookValidationResult(isValid: true);

        $this->expectException(Error::class);

        $result->isValid = false;
    }
}
