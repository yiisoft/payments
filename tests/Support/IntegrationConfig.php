<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Support;

/**
 * Loads integration test configuration from tests/config/*.php.
 *
 * - Copy *.php.dist to *.php and fill credentials/secrets.
 * - These files are ignored by git (see .gitignore).
 */
final class IntegrationConfig
{
    /**
     * @return array<string,mixed>|null
     */
    public static function load(string $name): ?array
    {
        $path = __DIR__ . '/../config/' . $name . '.php';

        if (!is_file($path)) {
            return null;
        }

        /** @var array<string,mixed> $config */
        $config = require $path;

        return $config;
    }
}
