<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookPayPalSignatureVerifierInterface;
use Yiisoft\Payments\Webhooks\WebhookPayPalValidator;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorRegistry;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookRobokassaValidator;
use Yiisoft\Payments\Webhooks\WebhookStripeProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookStripeValidator;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;
use Yiisoft\Payments\Webhooks\WebhookYooKassaProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookYooKassaValidator;

final class WebhookR1RawDataPreservationReadinessTest extends TestCase
{
    /**
     * @param array<string, mixed> $expectedPayload
     */
    #[DataProvider('rawDataPreservationProvider')]
    public function testR1PipelinePreservesRawRequestDataAcrossProviders(
        WebhookProcessor $processor,
        WebhookInput $input,
        string $expectedProviderEventType,
        array $expectedPayload,
    ): void {
        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame($input->providerId, $context->providerId);
        $this->assertNotNull($context->eventType);
        $this->assertNotNull($context->paymentStatus);
        $this->assertNull($context->validationFailureReason);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNull($context->unknownEventReason);

        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame($input->rawBody, $context->rawData->rawBody);
        $this->assertSame($input->headers, $context->rawData->headers);
        $this->assertSame($input->queryParams, $context->rawData->queryParams);
        $this->assertSame($input->bodyParams, $context->rawData->bodyParams);
        $this->assertSame($expectedProviderEventType, $context->rawData->providerEventType);
        $this->assertSame($expectedPayload, $context->rawData->payload);
    }

    /**
     * @return iterable<string, array{WebhookProcessor, WebhookInput, string, array<string, mixed>}>
     */
    public static function rawDataPreservationProvider(): iterable
    {
        yield 'stripe payment webhook' => self::stripeCase();
        yield 'paypal payment webhook' => self::paypalCase();
        yield 'yookassa payment webhook' => self::yookassaCase();
        yield 'robokassa result url callback' => self::robokassaCase();
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function stripeCase(): array
    {
        $signingSecret = 'whsec_r1_raw_data_secret';
        $timestamp = '1700000000';
        $payload = [
            'id' => 'evt_r1_raw_data',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_r1_raw_data',
                    'status' => 'succeeded',
                    'amount' => 1000,
                    'currency' => 'usd',
                ],
            ],
        ];
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $signingSecret);
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'Stripe-Signature' => 't=' . $timestamp . ',v1=' . $signature,
                'Content-Type' => 'application/json',
                'X-Debug-Trace' => ['stripe-r1-raw-data', 'preserve-header-list'],
            ],
            queryParams: [
                'endpoint' => 'payments',
                'debug' => 'stripe-r1-raw-data',
            ],
            bodyParams: [
                'ignored' => 'json-body-is-authoritative',
                'provider_form_marker' => 'stripe-preserved-body-param',
            ],
            providerId: 'stripe',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookStripeProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookStripeValidator(
                signingSecret: $signingSecret,
                currentTimestamp: (int) $timestamp,
            )),
        );

        return [$processor, $input, 'payment_intent.succeeded', $payload];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function paypalCase(): array
    {
        $payload = [
            'id' => 'WH-R1-RAW-DATA',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-R1-RAW-DATA',
                'status' => 'COMPLETED',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '10.00',
                ],
            ],
        ];
        $input = new WebhookInput(
            rawBody: json_encode($payload, JSON_THROW_ON_ERROR),
            headers: [
                'PayPal-Transmission-Id' => 'transmission-r1-raw-data',
                'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
                'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/r1-raw-data.pem',
                'PayPal-Auth-Algo' => 'SHA256withRSA',
                'PayPal-Transmission-Sig' => 'signature-r1-raw-data',
                'Content-Type' => 'application/json',
                'X-Debug-Trace' => ['paypal-r1-raw-data', 'preserve-header-list'],
            ],
            queryParams: [
                'endpoint' => 'payments',
                'debug' => 'paypal-r1-raw-data',
            ],
            bodyParams: [
                'ignored' => 'json-body-is-authoritative',
                'provider_form_marker' => 'paypal-preserved-body-param',
            ],
            providerId: 'paypal',
        );
        $signatureVerifier = new class implements WebhookPayPalSignatureVerifierInterface {
            public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
            {
                return WebhookValidationResult::success();
            }
        };
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookPayPalProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookPayPalValidator($signatureVerifier, 'WH-CONFIGURED-R1-RAW-DATA')),
        );

        return [$processor, $input, 'PAYMENT.CAPTURE.COMPLETED', $payload];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function yookassaCase(): array
    {
        $shopId = '123456';
        $secretKey = 'yookassa_r1_raw_data_secret';
        $payload = [
            'type' => 'notification',
            'event' => 'payment.succeeded',
            'object' => [
                'id' => 'payment-r1-raw-data',
                'status' => 'succeeded',
                'amount' => [
                    'value' => '10.00',
                    'currency' => 'RUB',
                ],
            ],
        ];
        $input = new WebhookInput(
            rawBody: json_encode($payload, JSON_THROW_ON_ERROR),
            headers: [
                'Authorization' => 'Basic ' . base64_encode($shopId . ':' . $secretKey),
                'Content-Type' => 'application/json',
                'X-Debug-Trace' => ['yookassa-r1-raw-data', 'preserve-header-list'],
            ],
            queryParams: [
                'endpoint' => 'payments',
                'debug' => 'yookassa-r1-raw-data',
            ],
            bodyParams: [
                'ignored' => 'json-body-is-authoritative',
                'provider_form_marker' => 'yookassa-preserved-body-param',
            ],
            providerId: 'yookassa',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookYooKassaProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookYooKassaValidator($shopId, $secretKey)),
        );

        return [$processor, $input, 'payment.succeeded', $payload];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function robokassaCase(): array
    {
        $password2 = 'robokassa_r1_raw_data_password2';
        $outSum = '100.00';
        $invId = '123';
        $orderId = 'order-r1-raw-data';
        $signature = md5($outSum . ':' . $invId . ':' . $password2 . ':Shp_orderId=' . $orderId);
        $bodyParams = [
            'OutSum' => $outSum,
            'InvId' => $invId,
            'SignatureValue' => $signature,
            'Shp_orderId' => $orderId,
        ];
        $queryParams = [
            'endpoint' => 'payments',
            'debug' => 'robokassa-r1-raw-data',
        ];
        $payload = $bodyParams + $queryParams;
        $input = new WebhookInput(
            rawBody: http_build_query($bodyParams),
            headers: [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Debug-Trace' => ['robokassa-r1-raw-data', 'preserve-header-list'],
            ],
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookRobokassaProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookRobokassaValidator($password2)),
        );

        return [$processor, $input, WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $payload];
    }
}
