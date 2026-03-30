<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Endpoints;

/**
 * Stripe API endpoints configuration.
 */
final class StripeEndpoints
{
    public function __construct(
        public readonly string $baseUri = 'https://api.stripe.com/v1',
    ) {
        self::assertHttpsUri($this->baseUri);
    }

    private static function assertHttpsUri(string $uri): void
    {
        if ($uri === '' || !str_starts_with($uri, 'https://')) {
            throw new \InvalidArgumentException('Endpoint URI must be a non-empty HTTPS URL.');
        }
    }
}
