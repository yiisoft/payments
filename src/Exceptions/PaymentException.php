<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Exceptions;

class PaymentException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly ?string $errorCode = null,
        public readonly ?string $errorType = null,
        public readonly ?string $declineCode = null,
        public readonly ?string $param = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
