<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;

/**
 * Registry and resolver for provider-specific webhook processors.
 */
final class ProviderWebhookProcessorRegistry
{
    /**
     * @var array<string, ProviderWebhookProcessorInterface>
     */
    private array $processors = [];

    public function __construct(ProviderWebhookProcessorInterface ...$processors)
    {
        foreach ($processors as $processor) {
            $providerId = $processor->getProviderId();

            if (trim($providerId) === '') {
                throw new InvalidArgumentException('Webhook provider processor ID must be a non-empty string.');
            }

            if (isset($this->processors[$providerId])) {
                throw new InvalidArgumentException(sprintf(
                    'Webhook provider processor with ID "%s" is already registered.',
                    $providerId,
                ));
            }

            $this->processors[$providerId] = $processor;
        }
    }

    public function get(string $providerId): ?ProviderWebhookProcessorInterface
    {
        return $this->processors[$providerId] ?? null;
    }

    public function has(string $providerId): bool
    {
        return isset($this->processors[$providerId]);
    }
}
