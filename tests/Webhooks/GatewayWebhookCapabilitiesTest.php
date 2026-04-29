<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Yiisoft\Payments\Gateways\PayPalGateway;
use Yiisoft\Payments\Gateways\RobokassaGateway;
use Yiisoft\Payments\Gateways\StripeGateway;
use Yiisoft\Payments\Gateways\YooKassaGateway;
use Yiisoft\Payments\Tests\Support\TestHttpClient;
use Yiisoft\Payments\Webhooks\WebhookCapabilitiesProviderInterface;
use Yiisoft\Payments\Webhooks\WebhookCapability;

final class GatewayWebhookCapabilitiesTest extends TestCase
{
    public function testAllGatewaysDeclareWebhookCapabilities(): void
    {
        foreach ($this->createGateways() as $gateway) {
            $this->assertInstanceOf(WebhookCapabilitiesProviderInterface::class, $gateway);

            $capabilities = $gateway->getWebhookCapabilities();

            $this->assertGreaterThan(0, $capabilities->count());
            $this->assertContainsOnlyInstancesOf(WebhookCapability::class, $capabilities->all());
        }
    }

    /**
     * @return list<WebhookCapabilitiesProviderInterface>
     */
    private function createGateways(): array
    {
        $psr17Factory = new Psr17Factory();
        $httpClient = new TestHttpClient($psr17Factory);
        $logger = new NullLogger();

        return [
            new StripeGateway(
                'test_api_key',
                $httpClient,
                $psr17Factory,
                $psr17Factory,
                $logger,
            ),
            new PayPalGateway(
                'test_client_id',
                'test_client_secret',
                true,
                $httpClient,
                $psr17Factory,
                $psr17Factory,
                $logger,
            ),
            new YooKassaGateway(
                'test_shop_id',
                'test_secret_key',
                $httpClient,
                $psr17Factory,
                $psr17Factory,
                $logger,
            ),
            new RobokassaGateway(
                'test_merchant_login',
                'test_password1',
                'test_password2',
                'test_password3',
                true,
                $httpClient,
                $psr17Factory,
                $psr17Factory,
                $logger,
            ),
        ];
    }
}
