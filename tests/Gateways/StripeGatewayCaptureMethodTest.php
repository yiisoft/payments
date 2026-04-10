<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Gateways;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Yiisoft\Payments\Gateways\StripeGateway;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Tests\Support\TestHttpClient;

final class StripeGatewayCaptureMethodTest extends TestCase
{
    private TestHttpClient $httpClient;
    private StripeGateway $gateway;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->httpClient = new TestHttpClient($factory);

        $this->gateway = new StripeGateway(
            apiKey: 'test_api_key',
            httpClient: $this->httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            logger: new NullLogger()
        );
    }

    public function testCreatePaymentIntentAutomaticCapture(): void
    {
        $this->httpClient->queueJsonResponse([
            'id' => 'pi_automatic',
            'object' => 'payment_intent',
            'amount' => 1500,
            'currency' => 'usd',
            'status' => 'requires_payment_method',
            'capture_method' => 'automatic',
        ]);

        $intent = new PaymentIntent(
            id: null,
            amount: 1500,
            currency: 'USD',
            captureMethod: false
        );

        $result = $this->gateway->createPaymentIntent($intent);

        $this->assertSame('pi_automatic', $result->id);

        parse_str($this->httpClient->lastRequest['body'], $body);
        $this->assertSame('automatic', $body['capture_method']);
    }
}
