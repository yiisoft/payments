<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Gateways\YooKassaGateway;
use Yiisoft\Payments\Tests\Support\TestHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;

class YooKassaGatewayTest extends TestCase
{
    private TestHttpClient $httpClient;
    private Psr17Factory $psr17Factory;
    private YooKassaGateway $gateway;

    protected function setUp(): void
    {
        $this->psr17Factory = new Psr17Factory();
        $this->httpClient = new TestHttpClient($this->psr17Factory);

        $this->gateway = new YooKassaGateway(
            'shop_ip',
            'secret_key',
            $this->httpClient,
            $this->psr17Factory,
            $this->psr17Factory,
            new NullLogger()
        );
    }

    private function withResponse(array $response): void
    {
        $this->httpClient->setNextResponse($response);
    }

    private function getLastRequest(): array
    {
        return $this->httpClient->lastRequest;
    }

    public function testCreateCustomer(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            name: 'Test User',
        );

        $created = $this->gateway->createCustomer($customer);

        $this->assertNull($created->id);
        $this->assertSame('test@example.com', $created->email);
        $this->assertSame('Test User', $created->name);
    }

    public function testCreatePaymentIntent(): void
    {
        $paymentIntent = new PaymentIntent(
            null,
            null,
            1000,
            'rub',
            'cus_test123',
            'bank_card',
            null,
            'Test payment',
            ['order_id' => '12345'],
            null,
            null,
            true,
            false,
            false,
            'test@example.com',
            'TEST'
        );

        $this->withResponse([
            "id" => "30ae77b9-000f-5001-8000-13e0de458932",
            "status" => "pending",
            "amount" => [
                "value" => "100.00",
                "currency" => "RUB",
            ],
            "description" => "Test payment",
            "recipient" => [
                "account_id" => "1183512",
                "gateway_id" => "2557477",
            ],
            "payment_method" => [
                "type" => "bank_card",
                "id" => "30ae77b9-000f-5001-8000-13e0de458932",
                "saved" => false,
                "status" => "inactive",
            ],
            "created_at" => "2025-11-18T12:18:01.563Z",
            "confirmation" => [
                "type" => "redirect",
                "return_url" => "https://5ebde85d10de.ngrok-free.app?payment=test_1",
                "confirmation_url" => "https://yoomoney.ru/checkout/payments/v2/contract?orderId=30ae77b9-000f-5001-8000-13e0de458932",
            ],
            "test" => true,
            "paid" => false,
            "refundable" => false,
            "metadata" => [],
        ]);

        $result = $this->gateway->createPaymentIntent($paymentIntent);

        $this->assertSame('30ae77b9-000f-5001-8000-13e0de458932', $result->id);
        $this->assertSame(10000, $result->amount);
        $this->assertSame('RUB', $result->currency);
        $this->assertSame('Test payment', $result->description);
    }

    public function testConfirmPaymentIntent(): void
    {
        $this->withResponse([
            "id" => "30ae77b9-000f-5001-8000-13e0de458932",
            "status" => "succeeded",
            "amount" => [
                "value" => "100.00",
                "currency" => "RUB",
            ],
            "income_amount" => [
                "value" => "96.50",
                "currency" => "RUB",
            ],
            "description" => "Order 1",
            "recipient" => [
                "account_id" => "1183512",
                "gateway_id" => "2557477",
            ],
            "payment_method" => [
                "type" => "bank_card",
                "id" => "30af50eb-000f-5001-8000-1533ca71a452",
                "saved" => false,
                "status" => "inactive",
                "title" => "Bank card *4477",
                "card" => [
                    "first6" => "555555",
                    "last4" => "4477",
                    "expiry_year" => "2011",
                    "expiry_month" => "11",
                    "card_type" => "MasterCard",
                    "card_product" => [
                        "code" => "E",
                    ],
                    "issuer_country" => "US",
                ],
            ],
            "captured_at" => "2025-11-19T03:51:05.783Z",
            "created_at" => "2025-11-19T03:44:43.200Z",
            "test" => true,
            "refunded_amount" => [
                "value" => "0.00",
                "currency" => "RUB",
            ],
            "paid" => true,
            "refundable" => true,
            "metadata" => [],
            "authorization_details" => [
                "rrn" => "166103673691924",
                "auth_code" => "758053",
                "three_d_secure" => [
                    "applied" => true,
                    "protocol" => "v1",
                    "method_completed" => false,
                    "challenge_completed" => true,
                ],
            ],
        ]);

        $result = $this->gateway->confirmPaymentIntent('30ae77b9-000f-5001-8000-13e0de458932', ['return_url' => 'https://example.com/return']);

        $this->assertSame('30ae77b9-000f-5001-8000-13e0de458932', $result->id);
        $this->assertSame('succeeded', $result->status);
    }

    public function testCreateRefund(): void
    {
        $this->withResponse([
            "id" => "30af6093-0015-5001-8000-196e1cbaceef",
            "payment_id" => "30af50eb-000f-5001-8000-1533ca71a452",
            "status" => "succeeded",
            "created_at" => "2025-11-19T04:51:31.067Z",
            "amount" => [
                "value" => "100.00",
                "currency" => "RUB"
            ],
            "refund_authorization_details" => [
                "rrn" => "325981525210676"
            ]
        ]);

        $result = $this->gateway->createRefund('30af50eb-000f-5001-8000-1533ca71a452', [
            'amount' => 1000,
            'currency' => 'rub'
        ]);

        $this->assertSame("30af6093-0015-5001-8000-196e1cbaceef", $result['id']);
        $this->assertSame(10000, $result['amount']);
        $this->assertSame('rub', $result['currency']);
        $this->assertSame('succeeded', $result['status']);
    }
}
