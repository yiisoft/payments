<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Endpoints;

/**
 * Robokassa API endpoints configuration.
 */
final class RobokassaEndpoints
{
    public function __construct(
        public readonly string $invoiceApiBaseUri = 'https://services.robokassa.ru/InvoiceServiceWebApi/api',
        public readonly string $refundApiBaseUri = 'https://services.robokassa.ru/RefundService/Refund',
        public readonly string $xmlApiBaseUri = 'https://auth.robokassa.ru/Merchant/WebService/Service.asmx',
    ) {
        self::assertHttpsUri($this->invoiceApiBaseUri);
        self::assertHttpsUri($this->refundApiBaseUri);
        self::assertHttpsUri($this->xmlApiBaseUri);
    }

    private static function assertHttpsUri(string $uri): void
    {
        if ($uri === '' || !str_starts_with($uri, 'https://')) {
            throw new \InvalidArgumentException('Endpoint URI must be a non-empty HTTPS URL.');
        }
    }
}
