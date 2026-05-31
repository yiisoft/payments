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
use Yiisoft\Payments\Webhooks\WebhookPaymentOutcomeRules;
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

final class WebhookR1RefundNormalizationBoundaryReadinessTest extends TestCase
{
    /**
     * @param array<string, mixed> $expectedPayload
     */
    #[DataProvider('refundLikeEventProvider')]
    public function testRefundLikeEventsStayOutsideR1NormalizationAfterSuccessfulValidation(
        WebhookProcessor $processor,
        WebhookInput $input,
        string $expectedProviderEventType,
        array $expectedPayload,
    ): void {
        $this->assertFalse(WebhookPaymentOutcomeRules::shouldProcess(WebhookEventType::PaymentRefunded));
        $this->assertTrue(WebhookPaymentOutcomeRules::shouldRejectAsUnsupported(WebhookEventType::PaymentRefunded));

        $context = $processor->process($input);

        $this->assertSame($input->providerId, $context->providerId);
        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $context->status);
        $this->assertNotSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $context->eventType);
        $this->assertNull($context->paymentStatus);
        $this->assertNull($context->validationFailureReason);
        $this->assertNotNull($context->unsupportedEventReason);
        $this->assertSame('unsupported_event_type', $context->unsupportedEventReason->code->value);
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

    public function testRobokassaDoesNotHaveRefundLikeR1CallbackNormalizationSignal(): void
    {
        $this->assertSame(WebhookEventType::PaymentSucceeded, WebhookRobokassaCallbackFormat::supportedR1PaymentOutcome());
        $this->assertTrue(WebhookRobokassaCallbackFormat::supportsR1PaymentOutcome(WebhookEventType::PaymentSucceeded));
        $this->assertFalse(WebhookRobokassaCallbackFormat::supportsR1PaymentOutcome(WebhookEventType::PaymentRefunded));
        $this->assertTrue(WebhookPaymentOutcomeRules::shouldRejectAsUnsupported(WebhookEventType::PaymentRefunded));
        $this->assertFalse(WebhookPaymentOutcomeRules::shouldProcess(WebhookEventType::PaymentRefunded));
    }

    /**
     * @return iterable<string, array{WebhookProcessor, WebhookInput, string, array<string, mixed>}>
     */
    public static function refundLikeEventProvider(): iterable
    {
        yield 'stripe charge.refunded remains outside R1 normalization' => self::stripeRefundedCase();
        yield 'paypal payment capture refunded remains outside R1 normalization' => self::paypalRefundedCase();
        yield 'paypal payment capture reversed remains outside R1 normalization' => self::paypalReversedCase();
        yield 'yookassa refund.succeeded remains outside R1 normalization' => self::yookassaRefundedCase();
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function stripeRefundedCase(): array
    {
        $signingSecret = 'whsec_r1_refund_boundary';
        $timestamp = '1700000000';
        $payload = [
            'id' => 'evt_r1_refund_boundary',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_r1_refund_boundary',
                    'status' => 'succeeded',
                    'refunded' => true,
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

        return [$processor, $input, 'charge.refunded', $payload];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function paypalRefundedCase(): array
    {
        return self::paypalCase(
            providerEventType: 'PAYMENT.CAPTURE.REFUNDED',
            resourceId: 'REFUND-R1-BOUNDARY',
            resourceStatus: 'REFUNDED',
        );
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function paypalReversedCase(): array
    {
        return self::paypalCase(
            providerEventType: 'PAYMENT.CAPTURE.REVERSED',
            resourceId: 'REVERSAL-R1-BOUNDARY',
            resourceStatus: 'REVERSED',
        );
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function paypalCase(
        string $providerEventType,
        string $resourceId,
        string $resourceStatus,
    ): array {
        $payload = [
            'id' => 'WH-R1-REFUND-BOUNDARY',
            'event_type' => $providerEventType,
            'resource' => [
                'id' => $resourceId,
                'status' => $resourceStatus,
            ],
        ];
        $input = new WebhookInput(
            rawBody: json_encode($payload, JSON_THROW_ON_ERROR),
            headers: [
                'PayPal-Transmission-Id' => 'transmission-r1-refund-boundary',
                'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
                'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/r1-refund-boundary.pem',
                'PayPal-Auth-Algo' => 'SHA256withRSA',
                'PayPal-Transmission-Sig' => 'signature-r1-refund-boundary',
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
            new WebhookProviderValidatorRegistry(new WebhookPayPalValidator($verifier, 'WH-CONFIGURED-R1-REFUND-BOUNDARY')),
        );

        return [$processor, $input, $providerEventType, $payload];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, array<string, mixed>}
     */
    private static function yookassaRefundedCase(): array
    {
        $shopId = 'shop-r1-refund-boundary';
        $secretKey = 'secret-r1-refund-boundary';
        $payload = [
            'type' => 'notification',
            'event' => 'refund.succeeded',
            'object' => [
                'id' => 'refund-r1-boundary',
                'status' => 'succeeded',
            ],
        ];
        $input = new WebhookInput(
            rawBody: json_encode($payload, JSON_THROW_ON_ERROR),
            headers: [
                'Authorization' => 'Basic ' . base64_encode($shopId . ':' . $secretKey),
                'Content-Type' => 'application/json',
            ],
            queryParams: ['endpoint' => 'payments'],
            bodyParams: ['ignored' => 'json-body-is-authoritative'],
            providerId: 'yookassa',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookYooKassaProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookYooKassaValidator($shopId, $secretKey)),
        );

        return [$processor, $input, 'refund.succeeded', $payload];
    }
}
