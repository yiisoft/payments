<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Yiisoft\Payments\Gateways\RobokassaGateway;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Tests\Support\IntegrationConfig;

final class RobokassaGatewayIntegrationTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $config = IntegrationConfig::load('robokassa');
        if ($config === null) {
            $this->markTestSkipped('Robokassa integration config missing. Copy tests/config/robokassa.php.dist to tests/config/robokassa.php and fill credentials.');
        }

        if (!class_exists(Psr18Client::class)) {
            $this->markTestSkipped('symfony/http-client is required for integration tests. Run: composer install --dev');
        }

        $this->config = $config;
    }

    /**
     * Variant A (default): create invoice and verify API connectivity.
     *
     * @group integration
     */
    public function testCreateInvoiceConnectivity(): void
    {
        $gateway = $this->createGateway();

        $intent = new PaymentIntent(
            id: null,
            amount: 100, // 1.00 (minor units)
            currency: 'RUB',
            description: 'Integration test invoice',
            metadata: [
                // Optional Robokassa fields:
                'InvoiceType' => 'OneTime',
                'Culture' => 'ru',
            ],
            captureMethod: false
        );

        $created = $gateway->createPaymentIntent($intent);

        $this->assertNotEmpty($created->id);
        $this->assertNotEmpty($created->status);

        if ($created->nextAction !== null) {
            $this->assertNotEmpty($created->nextAction['redirect_to_url']['url'] ?? null);
        }

        // Optional: retrieve status and OpKey if you have an invoice id (paid or existing).
        if (!empty($this->config['paid_invoice_id'])) {
            $status = $gateway->retrievePaymentIntent((string) $this->config['paid_invoice_id']);
            $this->assertNotEmpty($status->metadata['robokassa_state_code'] ?? null);
        }

        // Optional: refund requires Password#3 and a paid OpKey.
        if (!empty($this->config['paid_op_key'])) {
            $refund = $gateway->createRefund(
                paymentIntentId: (string) ($this->config['paid_invoice_id'] ?? $created->id),
                amount: 100
            );

            $this->assertTrue((bool) ($refund['success'] ?? false));
            $this->assertNotEmpty($refund['requestId'] ?? null);

            if (!empty($this->config['enable_refund_status_checks'])) {
                $status = $this->fetchRefundStatus((string) $refund['requestId']);
                $this->assertNotEmpty($status);
            }
        }
    }

    private function createGateway(): RobokassaGateway
    {
        $psr17 = new Psr17Factory();

        $symfonyClient = HttpClient::create([
            'timeout' => 30,
        ]);

        $psr18 = new Psr18Client($symfonyClient);

        return new RobokassaGateway(
            merchantLogin: (string) $this->config['merchant_login'],
            password1: (string) $this->config['password1'],
            password2: (string) $this->config['password2'],
            password3: (string) ($this->config['password3'] ?? ''),
            testMode: (bool) ($this->config['test_mode'] ?? true),
            httpClient: $psr18,
            requestFactory: $psr17,
            streamFactory: $psr17,
        );
    }

    /**
     * Best-effort Refund API status call.
     *
     * WARNING:
     * - Endpoint behavior may differ depending on merchant settings.
     * - This is enabled only when enable_refund_status_checks=true in config.
     *
     * @return array<string,mixed>
     */
    private function fetchRefundStatus(string $requestId): array
    {
        $psr17 = new Psr17Factory();
        $symfonyClient = HttpClient::create(['timeout' => 30]);
        $psr18 = new Psr18Client($symfonyClient);

        $url = 'https://services.robokassa.ru/RefundService/Refund/Status?requestId=' . urlencode($requestId);
        $request = $psr17->createRequest('GET', $url)->withHeader('Accept', 'application/json');

        $response = $psr18->sendRequest($request);

        $data = json_decode((string) $response->getBody(), true);
        return is_array($data) ? $data : [];
    }
}
