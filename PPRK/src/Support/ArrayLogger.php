<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Support;

use Psr\Log\AbstractLogger;

/**
 * Logger that stores log records in memory; useful for testing.
 */
final class ArrayLogger extends AbstractLogger
{
    /**
     * @var array<int,array{level:string,message:string,context:array<string,mixed>}>
     */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string)$level,
            'message' => (string)$message,
            'context' => $context,
        ];
    }
}
