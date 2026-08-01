<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Endpoints;

use InvalidArgumentException;

/**
 * YooKassa API endpoints configuration.
 */
final readonly class YooKassaEndpoints
{
    public function __construct(
        public string $baseUri = 'https://api.yookassa.ru/v3',
    ) {
        self::assertHttpsUri($this->baseUri);
    }

    private static function assertHttpsUri(string $uri): void
    {
        if ($uri === '' || !str_starts_with($uri, 'https://')) {
            throw new InvalidArgumentException('Endpoint URI must be a non-empty HTTPS URL.');
        }
    }
}
