<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Yiisoft\Payments\Gateways\PayPalGateway;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Tests\Support\IntegrationConfig;

final class PayPalGatewayIntegrationTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $config = IntegrationConfig::load('paypal');
        if ($config === null) {
            $this->markTestSkipped('PayPal integration config missing. Copy tests/config/paypal.php.dist to tests/config/paypal.php and fill credentials.');
        }

        if (!class_exists(Psr18Client::class)) {
            $this->markTestSkipped('symfony/http-client is required for integration tests. Run: composer install --dev');
        }

        $this->config = $config;
    }

    /**
     * Variant A (default): create order and verify API connectivity.
     *
     * @group integration
     */
    public function testCreateOrderConnectivity(): void
    {
        $gateway = $this->createGateway();

        $paymentIntent = new PaymentIntent(
            id: null,
            amount: 100, // $1.00
            currency: 'USD',
            metadata: [
                'return_url' => (string) ($this->config['return_url'] ?? 'https://example.com/return'),
                'cancel_url' => (string) ($this->config['cancel_url'] ?? 'https://example.com/cancel'),
                'order_id' => 'integration-test',
            ],
            captureMethod: false
        );

        $created = $gateway->createPaymentIntent($paymentIntent);

        $this->assertNotEmpty($created->id);
        $this->assertNotEmpty($created->status);

        // Approval URL is expected for web approval flows.
        if ($created->nextAction !== null) {
            $this->assertArrayHasKey('redirect_to_url', $created->nextAction);
            $this->assertNotEmpty($created->nextAction['redirect_to_url']['url']);
        }

        // Optional capture test: requires manually approved order id.
        if (!empty($this->config['approved_order_id'])) {
            $captured = $gateway->capturePaymentIntent((string) $this->config['approved_order_id']);
            $this->assertNotEmpty($captured->metadata['capture_id'] ?? null);
        }

        // Optional refund test: requires capture id.
        if (!empty($this->config['capture_id_for_refund'])) {
            $refund = $gateway->createRefund(
                paymentIntentId: (string) $this->config['capture_id_for_refund'],
                amount: 100,
                params: ['currency' => 'USD', 'note_to_payer' => 'Integration test refund']
            );

            $this->assertNotEmpty($refund['id'] ?? null);
        }
    }

    /**
     * Variant B (optional): advanced card payments (confirm payment source) + capture.
     *
     * Enable it by setting enable_advanced_card_payments_flow=true in tests/config/paypal.php.
     *
     * @group integration
     */
    public function testAdvancedCardPaymentsFlowOptional(): void
    {
        if (empty($this->config['enable_advanced_card_payments_flow'])) {
            $this->markTestSkipped('Advanced card payments flow disabled in config.');
        }

        if (empty($this->config['payment_source']) || !is_array($this->config['payment_source'])) {
            $this->markTestSkipped('payment_source is not configured.');
        }

        $gateway = $this->createGateway();

        $paymentIntent = new PaymentIntent(
            id: null,
            amount: 100,
            currency: 'USD',
            metadata: [
                'return_url' => (string) ($this->config['return_url'] ?? 'https://example.com/return'),
                'cancel_url' => (string) ($this->config['cancel_url'] ?? 'https://example.com/cancel'),
            ],
            captureMethod: false
        );

        $created = $gateway->createPaymentIntent($paymentIntent);
        $this->assertNotEmpty($created->id);

        $captured = $gateway->capturePaymentIntent($created->id, [
            'confirm_payment_source' => true,
            'payment_source' => $this->config['payment_source'],
        ]);

        $this->assertNotEmpty($captured->metadata['capture_id'] ?? null);
    }

    private function createGateway(): PayPalGateway
    {
        $psr17 = new Psr17Factory();

        $symfonyClient = HttpClient::create([
            'timeout' => 30,
        ]);

        $psr18 = new Psr18Client($symfonyClient);

        return new PayPalGateway(
            clientId: (string) $this->config['client_id'],
            clientSecret: (string) $this->config['client_secret'],
            sandbox: (bool) ($this->config['sandbox'] ?? true),
            httpClient: $psr18,
            requestFactory: $psr17,
            streamFactory: $psr17,
        );
    }
}
