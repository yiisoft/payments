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
use Yiisoft\Payments\Webhooks\WebhookStripeProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookStripeValidator;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;
use Yiisoft\Payments\Webhooks\WebhookYooKassaProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookYooKassaValidator;

final class WebhookR1UnsupportedEventReadinessTest extends TestCase
{
    /**
     * @param array<string, mixed> $expectedPayload
     */
    #[DataProvider('unsupportedEventProvider')]
    public function testR1PipelineReturnsUnsupportedEventContextAfterSuccessfulValidation(
        WebhookProcessor $processor,
        WebhookInput $input,
        string $expectedProviderEventType,
        array $expectedPayload,
    ): void {
        $context = $processor->process($input);

        $this->assertSame($input->providerId, $context->providerId);
        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $context->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $context->eventType);
        $this->assertNull($context->paymentStatus);
        $this->assertNull($context->validationFailureReason);
        $this->assertNotNull($context->unsupportedEventReason);
        $this->assertSame('unsupported_event_type', $context->unsupportedEventReason->code->value);
        $this->assertSame(
            'Webhook event type is recognized but is not supported by the current webhook contract.',
            $context->unsupportedEventReason->message,
        );
        $this->assertSame($expectedProviderEventType, $context->unsupportedEventReason->providerEventType);
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

    public function testRobokassaR1PipelineHasNoSeparateUnsupportedPaymentOutcomeSignal(): void
    {
        $this->assertSame(WebhookEventType::PaymentSucceeded, WebhookRobokassaCallbackFormat::supportedR1PaymentOutcome());
        $this->assertTrue(WebhookRobokassaCallbackFormat::supportsR1PaymentOutcome(WebhookEventType::PaymentSucceeded));
        $this->assertFalse(WebhookRobokassaCallbackFormat::supportsR1PaymentOutcome(WebhookEventType::PaymentRefunded));
        $this->assertFalse(WebhookRobokassaCallbackFormat::supportsR1PaymentOutcome(WebhookEventType::PaymentFailed));
        $this->assertFalse(WebhookRobokassaCallbackFormat::supportsR1PaymentOutcome(WebhookEventType::PaymentCanceled));
        $this->assertFalse(WebhookRobokassaCallbackFormat::supportsR1PaymentOutcome(WebhookEventType::PaymentProcessing));
        $this->assertFalse(WebhookRobokassaCallbackFormat::supportsR1PaymentOutcome(WebhookEventType::PaymentRequiresCapture));
    }

    /**
     * @return iterable<string, array{WebhookProcessor, WebhookInput, string, array<string, mixed>}>
     */
    public static function unsupportedEventProvider(): iterable
    {
        yield 'stripe refund-like event outside R1 normalization' => self::stripeRefundLikeEventCase();
        yield 'paypal refund-like event outside R1 normalization' => self::paypalRefundLikeEventCase();
        yield 'yookassa refund-like event outside R1 normalization' => self::yookassaRefundLikeEventCase();
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function stripeRefundLikeEventCase(): array
    {
        $signingSecret = 'whsec_unsupported_event';
        $timestamp = '1700000000';
        $providerEventType = 'charge.refunded';
        $rawBody = '{"id":"evt_unsupported_r1","type":"charge.refunded","data":{"object":{"id":"ch_unsupported_r1","status":"succeeded"}}}';
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $signingSecret);
        $payload = [
            'id' => 'evt_unsupported_r1',
            'type' => 'charge.refunded',
            'data' => ['object' => ['id' => 'ch_unsupported_r1', 'status' => 'succeeded']],
        ];
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'Stripe-Signature' => 't=' . $timestamp . ',v1=' . $signature,
                'Content-Type' => 'application/json',
            ],
            queryParams: ['endpoint' => 'payments'],
            bodyParams: ['ignored' => 'json-body-is-authoritative'],
            providerId: 'stripe',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookStripeProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookStripeValidator(
                signingSecret: $signingSecret,
                currentTimestamp: (int) $timestamp,
            )),
        );

        return [$processor, $input, $providerEventType, $payload];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function paypalRefundLikeEventCase(): array
    {
        $providerEventType = 'PAYMENT.CAPTURE.REFUNDED';
        $rawBody = '{"id":"WH-UNSUPPORTED-R1","event_type":"PAYMENT.CAPTURE.REFUNDED","resource":{"id":"REFUND-UNSUPPORTED-R1","status":"REFUNDED"}}';
        $payload = [
            'id' => 'WH-UNSUPPORTED-R1',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => ['id' => 'REFUND-UNSUPPORTED-R1', 'status' => 'REFUNDED'],
        ];
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'PayPal-Transmission-Id' => 'transmission-unsupported-r1',
                'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
                'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/unsupported-r1.pem',
                'PayPal-Auth-Algo' => 'SHA256withRSA',
                'PayPal-Transmission-Sig' => 'signature-unsupported-r1',
                'Content-Type' => 'application/json',
            ],
            queryParams: ['endpoint' => 'payments'],
            bodyParams: ['ignored' => 'json-body-is-authoritative'],
            providerId: 'paypal',
        );
        $verifier = new class implements WebhookPayPalSignatureVerifierInterface {
            public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
            {
                return WebhookValidationResult::success();
            }
        };
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookPayPalProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookPayPalValidator($verifier, 'WH-CONFIGURED-UNSUPPORTED-R1')),
        );

        return [$processor, $input, $providerEventType, $payload];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function yookassaRefundLikeEventCase(): array
    {
        $providerEventType = 'refund.succeeded';
        $rawBody = '{"type":"notification","event":"refund.succeeded","object":{"id":"refund-unsupported-r1","status":"succeeded"}}';
        $payload = [
            'type' => 'notification',
            'event' => 'refund.succeeded',
            'object' => ['id' => 'refund-unsupported-r1', 'status' => 'succeeded'],
        ];
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'Authorization' => 'Basic ' . base64_encode('shop-id:secret-key'),
                'Content-Type' => 'application/json',
            ],
            queryParams: ['endpoint' => 'payments'],
            bodyParams: ['ignored' => 'json-body-is-authoritative'],
            providerId: 'yookassa',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookYooKassaProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookYooKassaValidator('shop-id', 'secret-key')),
        );

        return [$processor, $input, $providerEventType, $payload];
    }
}
