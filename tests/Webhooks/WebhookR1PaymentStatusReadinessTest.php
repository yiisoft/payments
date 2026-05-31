<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
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

final class WebhookR1PaymentStatusReadinessTest extends TestCase
{
    #[DataProvider('processedPaymentStatusProvider')]
    public function testR1PipelineExposesPublicPaymentStatusAcrossProviders(
        WebhookProcessor $processor,
        WebhookInput $input,
        WebhookEventType $expectedEventType,
        string $expectedPaymentStatus,
        string $expectedProviderEventType,
    ): void {
        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame($expectedEventType, $context->eventType);
        $this->assertSame($expectedPaymentStatus, $context->paymentStatus);
        $this->assertNull($context->validationFailureReason);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNull($context->unknownEventReason);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame($expectedProviderEventType, $context->rawData->providerEventType);
    }

    /**
     * @return iterable<string, array{WebhookProcessor, WebhookInput, WebhookEventType, string, string}>
     */
    public static function processedPaymentStatusProvider(): iterable
    {
        yield 'stripe succeeded status' => self::stripeCase(
            providerEventType: 'payment_intent.succeeded',
            eventType: WebhookEventType::PaymentSucceeded,
            paymentStatus: 'succeeded',
        );
        yield 'stripe failed status' => self::stripeCase(
            providerEventType: 'payment_intent.payment_failed',
            eventType: WebhookEventType::PaymentFailed,
            paymentStatus: 'requires_payment_method',
        );
        yield 'stripe requires capture status' => self::stripeCase(
            providerEventType: 'payment_intent.amount_capturable_updated',
            eventType: WebhookEventType::PaymentRequiresCapture,
            paymentStatus: 'requires_capture',
        );
        yield 'paypal completed status' => self::paypalCase(
            providerEventType: 'PAYMENT.CAPTURE.COMPLETED',
            eventType: WebhookEventType::PaymentSucceeded,
            paymentStatus: 'COMPLETED',
        );
        yield 'paypal denied status' => self::paypalCase(
            providerEventType: 'PAYMENT.CAPTURE.DENIED',
            eventType: WebhookEventType::PaymentFailed,
            paymentStatus: 'DENIED',
        );
        yield 'paypal authorization status' => self::paypalCase(
            providerEventType: 'PAYMENT.AUTHORIZATION.CREATED',
            eventType: WebhookEventType::PaymentRequiresCapture,
            paymentStatus: 'CREATED',
        );
        yield 'yookassa succeeded status' => self::yookassaCase(
            providerEventType: 'payment.succeeded',
            eventType: WebhookEventType::PaymentSucceeded,
            paymentStatus: 'succeeded',
        );
        yield 'yookassa canceled status' => self::yookassaCase(
            providerEventType: 'payment.canceled',
            eventType: WebhookEventType::PaymentCanceled,
            paymentStatus: 'canceled',
        );
        yield 'yookassa waiting for capture status' => self::yookassaCase(
            providerEventType: 'payment.waiting_for_capture',
            eventType: WebhookEventType::PaymentRequiresCapture,
            paymentStatus: 'waiting_for_capture',
        );
        yield 'robokassa result url status' => self::robokassaCase();
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, WebhookEventType, string, string}
     */
    private static function stripeCase(
        string $providerEventType,
        WebhookEventType $eventType,
        string $paymentStatus,
    ): array {
        $signingSecret = 'whsec_r1_payment_status_secret';
        $timestamp = '1700000000';
        $payload = [
            'id' => 'evt_r1_payment_status',
            'type' => $providerEventType,
            'data' => [
                'object' => [
                    'id' => 'pi_r1_payment_status',
                    'status' => $paymentStatus,
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

        return [$processor, $input, $eventType, $paymentStatus, $providerEventType];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, WebhookEventType, string, string}
     */
    private static function paypalCase(
        string $providerEventType,
        WebhookEventType $eventType,
        string $paymentStatus,
    ): array {
        $payload = [
            'id' => 'WH-R1-PAYMENT-STATUS',
            'event_type' => $providerEventType,
            'resource' => [
                'id' => 'PAYPAL-R1-PAYMENT-STATUS',
                'status' => $paymentStatus,
            ],
        ];
        $input = new WebhookInput(
            rawBody: json_encode($payload, JSON_THROW_ON_ERROR),
            headers: [
                'PayPal-Transmission-Id' => 'transmission-r1-payment-status',
                'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
                'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/r1-payment-status.pem',
                'PayPal-Auth-Algo' => 'SHA256withRSA',
                'PayPal-Transmission-Sig' => 'signature-r1-payment-status',
                'Content-Type' => 'application/json',
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
            new WebhookProviderValidatorRegistry(new WebhookPayPalValidator(
                signatureVerifier: $signatureVerifier,
                webhookId: 'WH-R1-PAYMENT-STATUS',
            )),
        );

        return [$processor, $input, $eventType, $paymentStatus, $providerEventType];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, WebhookEventType, string, string}
     */
    private static function yookassaCase(
        string $providerEventType,
        WebhookEventType $eventType,
        string $paymentStatus,
    ): array {
        $shopId = '123456';
        $secretKey = 'yookassa_r1_payment_status_secret';
        $payload = [
            'type' => 'notification',
            'event' => $providerEventType,
            'object' => [
                'id' => 'payment-r1-payment-status',
                'status' => $paymentStatus,
            ],
        ];
        $input = new WebhookInput(
            rawBody: json_encode($payload, JSON_THROW_ON_ERROR),
            headers: [
                'Authorization' => 'Basic ' . base64_encode($shopId . ':' . $secretKey),
                'Content-Type' => 'application/json',
            ],
            providerId: 'yookassa',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookYooKassaProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookYooKassaValidator($shopId, $secretKey)),
        );

        return [$processor, $input, $eventType, $paymentStatus, $providerEventType];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, WebhookEventType, string, string}
     */
    private static function robokassaCase(): array
    {
        $password2 = 'robokassa_r1_payment_status_password2';
        $outSum = '100.00';
        $invId = '123';
        $orderId = 'order-r1-payment-status';
        $signature = md5($outSum . ':' . $invId . ':' . $password2 . ':Shp_orderId=' . $orderId);
        $bodyParams = [
            'OutSum' => $outSum,
            'InvId' => $invId,
            'SignatureValue' => $signature,
            'Shp_orderId' => $orderId,
        ];
        $input = new WebhookInput(
            rawBody: http_build_query($bodyParams),
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            bodyParams: $bodyParams,
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookRobokassaProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookRobokassaValidator($password2)),
        );

        return [
            $processor,
            $input,
            WebhookEventType::PaymentSucceeded,
            WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
        ];
    }
}
