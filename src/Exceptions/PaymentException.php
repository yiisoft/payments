<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Exceptions;

class PaymentException extends \RuntimeException
{
    /**
     * @param array<string,mixed>|null $details
     */
    public function __construct(
        string $message = '',
        public readonly ?string $errorCode = null,
        public readonly ?string $errorType = null,
        public readonly ?string $declineCode = null,
        public readonly ?string $param = null,
        public readonly ?array $details = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
