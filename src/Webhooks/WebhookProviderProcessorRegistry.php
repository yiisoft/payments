<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;

/**
 * Registry and resolver for provider-specific webhook processors.
 */
final class WebhookProviderProcessorRegistry
{
    /**
     * @var array<string, WebhookProviderProcessorInterface>
     */
    private array $processors = [];

    public function __construct(WebhookProviderProcessorInterface ...$processors)
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

    public function get(string $providerId): ?WebhookProviderProcessorInterface
    {
        return $this->processors[$providerId] ?? null;
    }

    public function missingProcessorResult(string $providerId, ?WebhookRawData $rawData = null): WebhookProcessingResult
    {
        return WebhookProcessingResult::missingProviderProcessor($providerId, $rawData);
    }

    public function has(string $providerId): bool
    {
        return isset($this->processors[$providerId]);
    }
}
