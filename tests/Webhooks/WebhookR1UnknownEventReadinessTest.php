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
use Yiisoft\Payments\Webhooks\WebhookStripeProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookStripeValidator;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;
use Yiisoft\Payments\Webhooks\WebhookYooKassaProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookYooKassaValidator;

final class WebhookR1UnknownEventReadinessTest extends TestCase
{
    #[DataProvider('unknownEventProvider')]
    public function testR1PipelineReturnsUnknownEventContextAfterSuccessfulValidation(
        WebhookProcessor $processor,
        WebhookInput $input,
        string $expectedProviderEventType,
    ): void {
        $context = $processor->process($input);

        $this->assertSame($input->providerId, $context->providerId);
        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNull($context->paymentStatus);
        $this->assertNull($context->validationFailureReason);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNotNull($context->unknownEventReason);
        $this->assertSame('unknown_event_type', $context->unknownEventReason->code->value);
        $this->assertSame(
            'Provider event type is not recognized by the webhook event mapping.',
            $context->unknownEventReason->message,
        );
        $this->assertSame($expectedProviderEventType, $context->unknownEventReason->providerEventType);
        $this->assertSame($input, $context->rawInput);
        $this->assertNull($context->rawData);
    }

    /**
     * @return iterable<string, array{WebhookProcessor, WebhookInput, string}>
     */
    public static function unknownEventProvider(): iterable
    {
        yield 'stripe unknown payment event' => self::stripeUnknownEventCase();
        yield 'paypal unknown payment event' => self::paypalUnknownEventCase();
        yield 'yookassa unknown payment event' => self::yookassaUnknownEventCase();
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string}
     */
    private static function stripeUnknownEventCase(): array
    {
        $signingSecret = 'whsec_unknown_event';
        $timestamp = '1700000000';
        $providerEventType = 'payment_intent.future_r1_event';
        $rawBody = '{"id":"evt_unknown_r1","type":"payment_intent.future_r1_event","data":{"object":{"id":"pi_unknown_r1","status":"future"}}}';
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

        return [$processor, $input, $providerEventType];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string}
     */
    private static function paypalUnknownEventCase(): array
    {
        $providerEventType = 'PAYMENT.CAPTURE.FUTURE_R1_EVENT';
        $rawBody = '{"id":"WH-UNKNOWN-R1","event_type":"PAYMENT.CAPTURE.FUTURE_R1_EVENT","resource":{"id":"CAPTURE-UNKNOWN-R1","status":"FUTURE"}}';
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'PayPal-Transmission-Id' => 'transmission-unknown-r1',
                'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
                'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/unknown-r1.pem',
                'PayPal-Auth-Algo' => 'SHA256withRSA',
                'PayPal-Transmission-Sig' => 'signature-unknown-r1',
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
            new WebhookProviderValidatorRegistry(new WebhookPayPalValidator($verifier, 'WH-CONFIGURED-UNKNOWN-R1')),
        );

        return [$processor, $input, $providerEventType];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string}
     */
    private static function yookassaUnknownEventCase(): array
    {
        $providerEventType = 'payment.future_r1_event';
        $rawBody = '{"type":"notification","event":"payment.future_r1_event","object":{"id":"payment-unknown-r1","status":"future"}}';
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

        return [$processor, $input, $providerEventType];
    }
}
