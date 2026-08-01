<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Endpoints;

use InvalidArgumentException;

/**
 * PayPal API endpoints configuration.
 */
final readonly class PayPalEndpoints
{
    public function __construct(
        public string $sandboxBaseUri = 'https://api-m.sandbox.paypal.com',
        public string $liveBaseUri = 'https://api-m.paypal.com',
    ) {
        self::assertHttpsUri($this->sandboxBaseUri);
        self::assertHttpsUri($this->liveBaseUri);
    }

    public function getBaseUri(bool $sandbox): string
    {
        return $sandbox ? $this->sandboxBaseUri : $this->liveBaseUri;
    }

    private static function assertHttpsUri(string $uri): void
    {
        if ($uri === '' || !str_starts_with($uri, 'https://')) {
            throw new InvalidArgumentException('Endpoint URI must be a non-empty HTTPS URL.');
        }
    }
}
