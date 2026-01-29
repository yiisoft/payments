<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Endpoints;

/**
 * PayPal API endpoints configuration.
 */
final class PayPalEndpoints
{
    public function __construct(
        public readonly string $sandboxBaseUri = 'https://api-m.sandbox.paypal.com',
        public readonly string $liveBaseUri = 'https://api-m.paypal.com',
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
            throw new \InvalidArgumentException('Endpoint URI must be a non-empty HTTPS URL.');
        }
    }
}
