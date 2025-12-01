<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Gateway;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Payments\Model\PaymentMethod;

/**
 * Base class for gateways with shared HTTP and logging dependencies.
 */
abstract class AbstractGateway implements PaymentGatewayInterface
{
    /**
     * @param PaymentMethod $paymentMethod Method configuration for the gateway.
     * @param array<string,mixed> $config Provider configuration (urls, credentials, etc.).
     */
    public function __construct(
        protected PaymentMethod $paymentMethod,
        protected array $config,
        protected ClientInterface $http,
        protected RequestFactoryInterface $requestFactory,
        protected StreamFactoryInterface $streamFactory,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * Helper to read configuration values.
     *
     * @param string $key Configuration key.
     * @param mixed $default Default value if config key is not set.
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
