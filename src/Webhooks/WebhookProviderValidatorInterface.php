<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific webhook request validator.
 */
interface WebhookProviderValidatorInterface
{
    public function getProviderId(): string;

    public function validate(WebhookInput $input): WebhookValidationResult;
}
