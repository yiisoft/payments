<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks\Support;

use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;

/**
 * Test-only provider validator that always returns a failed validation result.
 */
final class FailedWebhookProviderValidator implements WebhookProviderValidatorInterface
{
    public int $validateCalls = 0;
    public ?WebhookInput $validatedInput = null;

    public function __construct(
        private readonly string $providerId = 'test-provider',
    ) {
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function validate(WebhookInput $input): WebhookValidationResult
    {
        $this->validateCalls++;
        $this->validatedInput = $input;

        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode('test_validation_failed'),
            message: 'Test webhook validation failed.',
        ));
    }
}
