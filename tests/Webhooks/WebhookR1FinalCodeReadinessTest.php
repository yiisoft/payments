<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Endpoints\PayPalEndpoints;
use Yiisoft\Payments\Gateways\PayPalGateway;
use Yiisoft\Payments\Tests\Support\TestHttpClient;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookPayPalPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookPayPalSignatureVerifier;
use Yiisoft\Payments\Webhooks\WebhookPayPalSignatureVerifierInterface;
use Yiisoft\Payments\Webhooks\WebhookPayPalValidator;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookStripePaymentWebhookMapper;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPaymentWebhookMapper;

final class WebhookR1FinalCodeReadinessTest extends TestCase
{
    public function testPayPalGatewayProvidesDefaultR1WebhookValidationSetup(): void
    {
        $factory = new Psr17Factory();
        $gateway = new PayPalGateway(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            sandbox: true,
            httpClient: new TestHttpClient($factory),
            requestFactory: $factory,
            streamFactory: $factory,
            endpoints: new PayPalEndpoints(
                sandboxBaseUri: 'https://paypal-sandbox.test',
                liveBaseUri: 'https://paypal-live.test',
            ),
        );

        $signatureVerifier = $gateway->createWebhookSignatureVerifier();
        $validator = $gateway->createWebhookValidator('WH-R1-READY');

        $this->assertInstanceOf(WebhookPayPalSignatureVerifier::class, $signatureVerifier);
        $this->assertInstanceOf(WebhookPayPalSignatureVerifierInterface::class, $signatureVerifier);
        $this->assertInstanceOf(WebhookPayPalValidator::class, $validator);
    }

    #[DataProvider('unknownEventMapperProvider')]
    public function testUnknownPaymentWebhookMappersPreserveRawDataForR1Debugging(
        PaymentWebhookMapperInterface $mapper,
        string $providerId,
        string $providerEventType,
        array $payloadData,
    ): void {
        $rawData = new WebhookRawData(
            rawBody: json_encode($payloadData, JSON_THROW_ON_ERROR),
            headers: ['Content-Type' => 'application/json'],
            payload: $payloadData,
            providerEventType: $providerEventType,
            queryParams: ['endpoint' => 'payments'],
            bodyParams: ['ignored' => 'json-body-is-authoritative'],
        );

        $result = $mapper->mapPaymentWebhook(new WebhookPayload(
            providerId: $providerId,
            eventType: null,
            providerEventType: $providerEventType,
            data: $payloadData,
            rawData: $rawData,
        ));

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertSame($rawData, $result->rawData);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame($providerEventType, $result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{PaymentWebhookMapperInterface, string, string, array<string, mixed>}>
     */
    public static function unknownEventMapperProvider(): iterable
    {
        yield 'stripe' => [
            new WebhookStripePaymentWebhookMapper(),
            'stripe',
            'payment_intent.future_r1_event',
            [
                'id' => 'evt_final_ready_stripe',
                'type' => 'payment_intent.future_r1_event',
                'data' => ['object' => ['id' => 'pi_final_ready_stripe', 'status' => 'future']],
            ],
        ];

        yield 'paypal' => [
            new WebhookPayPalPaymentWebhookMapper(),
            'paypal',
            'PAYMENT.CAPTURE.FUTURE_R1_EVENT',
            [
                'id' => 'WH-FINAL-READY-PAYPAL',
                'event_type' => 'PAYMENT.CAPTURE.FUTURE_R1_EVENT',
                'resource' => ['id' => 'CAPTURE-FINAL-READY-PAYPAL', 'status' => 'FUTURE'],
            ],
        ];

        yield 'yookassa' => [
            new WebhookYooKassaPaymentWebhookMapper(),
            'yookassa',
            'payment.future_r1_event',
            [
                'type' => 'notification',
                'event' => 'payment.future_r1_event',
                'object' => ['id' => 'payment-final-ready-yookassa', 'status' => 'future'],
            ],
        ];

        yield 'robokassa' => [
            new WebhookRobokassaPaymentWebhookMapper(),
            WebhookRobokassaCallbackFormat::PROVIDER_ID,
            'robokassa.future_r1_event',
            [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature-final-ready-robokassa',
            ],
        ];
    }
}
