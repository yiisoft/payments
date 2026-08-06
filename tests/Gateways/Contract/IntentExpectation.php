<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways\Contract;

final readonly class IntentExpectation
{
    public function __construct(
        public string $id,
        public int $amount,
        public string $currency,
        public string $status,
    ) {
    }
}
