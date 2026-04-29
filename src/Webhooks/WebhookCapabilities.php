<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Immutable collection of normalized webhook capabilities declared by a gateway.
 *
 * @implements IteratorAggregate<int, WebhookCapability>
 */
final readonly class WebhookCapabilities implements Countable, IteratorAggregate
{
    /**
     * @var list<WebhookCapability>
     */
    private array $capabilities;

    public function __construct(WebhookCapability ...$capabilities)
    {
        $this->capabilities = $capabilities;
    }

    /**
     * @return list<WebhookCapability>
     */
    public function all(): array
    {
        return $this->capabilities;
    }

    /**
     * Creates an unsupported processing result when the declared capability explicitly marks the event as unsupported.
     */
    public function unsupportedResultFor(
        WebhookEventType $eventType,
        WebhookEntityKind $entityKind,
        ?string $providerEventType = null,
    ): ?WebhookProcessingResult {
        foreach ($this->capabilities as $capability) {
            if ($capability->eventType !== $eventType || $capability->entityKind !== $entityKind) {
                continue;
            }

            if ($capability->supportStatus === WebhookSupportStatus::Unsupported) {
                return WebhookProcessingResult::unsupportedEvent($eventType, $providerEventType);
            }

            return null;
        }

        return null;
    }

    public function count(): int
    {
        return count($this->capabilities);
    }

    /**
     * @return Traversable<int, WebhookCapability>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->capabilities);
    }
}
