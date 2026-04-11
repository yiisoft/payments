<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Exceptions;

class InvalidRequestException extends PaymentException
{
    /**
     * @param array<string,mixed>|null $details
     */
    public function __construct(
        string $message = 'Invalid request',
        ?string $errorCode = null,
        ?string $errorType = null,
        ?string $declineCode = null,
        ?string $param = null,
        ?array $details = null,
        int $code = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $errorType, $declineCode, $param, $details, $code, $previous);
    }
}
