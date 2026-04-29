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
