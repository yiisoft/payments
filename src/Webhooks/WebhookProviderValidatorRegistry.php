<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;

/**
 * Registry and resolver for provider-specific webhook validators.
 */
final class WebhookProviderValidatorRegistry
{
    /**
     * @var array<string, WebhookProviderValidatorInterface>
     */
    private array $validators = [];

    public function __construct(WebhookProviderValidatorInterface ...$validators)
    {
        foreach ($validators as $validator) {
            $providerId = $validator->getProviderId();

            if (trim($providerId) === '') {
                throw new InvalidArgumentException('Webhook provider validator ID must be a non-empty string.');
            }

            if (isset($this->validators[$providerId])) {
                throw new InvalidArgumentException(sprintf(
                    'Webhook provider validator with ID "%s" is already registered.',
                    $providerId,
                ));
            }

            $this->validators[$providerId] = $validator;
        }
    }

    public function get(string $providerId): ?WebhookProviderValidatorInterface
    {
        return $this->validators[$providerId] ?? null;
    }

    public function has(string $providerId): bool
    {
        return isset($this->validators[$providerId]);
    }
}
