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
use Yiisoft\Payments\Webhooks\WebhookRobokassaProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookRobokassaValidator;
use Yiisoft\Payments\Webhooks\WebhookStripeProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookStripeValidator;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;
use Yiisoft\Payments\Webhooks\WebhookYooKassaProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookYooKassaValidator;

final class WebhookR1ValidationFailureReadinessTest extends TestCase
{
    /**
     * @param array<string, string|list<string>> $expectedHeaders
     * @param array<string, mixed> $expectedQueryParams
     * @param array<string, mixed> $expectedBodyParams
     */
    #[DataProvider('validationFailureProvider')]
    public function testR1PipelineReturnsValidationFailedContextBeforeProviderProcessing(
        WebhookProcessor $processor,
        WebhookInput $input,
        string $expectedReasonCode,
        string $expectedReasonMessage,
        ?string $expectedProviderEventType,
        array $expectedHeaders,
        array $expectedQueryParams,
        array $expectedBodyParams,
    ): void {
        $context = $processor->process($input);

        $this->assertSame($input->providerId, $context->providerId);
        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNull($context->paymentStatus);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame($expectedReasonCode, $context->validationFailureReason->code->value);
        $this->assertSame($expectedReasonMessage, $context->validationFailureReason->message);
        $this->assertSame($expectedProviderEventType, $context->validationFailureReason->providerEventType);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNull($context->unknownEventReason);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame($input->rawBody, $context->rawData->rawBody);
        $this->assertSame($expectedHeaders, $context->rawData->headers);
        $this->assertSame($expectedQueryParams, $context->rawData->queryParams);
        $this->assertSame($expectedBodyParams, $context->rawData->bodyParams);
        $this->assertNull($context->rawData->payload);
        $this->assertNull($context->rawData->providerEventType);
    }

    /**
     * @return iterable<string, array{WebhookProcessor, WebhookInput, string, string, ?string, array<string, string|list<string>>, array<string, mixed>, array<string, mixed>}>
     */
    public static function validationFailureProvider(): iterable
    {
        yield 'stripe invalid signature' => self::stripeInvalidSignatureCase();
        yield 'paypal missing authenticity marker' => self::paypalMissingAuthenticityMarkerCase();
        yield 'yookassa invalid basic auth' => self::yookassaInvalidBasicAuthCase();
        yield 'robokassa invalid signature' => self::robokassaInvalidSignatureCase();
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, string, ?string, array<string, string|list<string>>, array<string, mixed>, array<string, mixed>}
     */
    private static function stripeInvalidSignatureCase(): array
    {
        $rawBody = '{"id":"evt_validation_failed","type":"payment_intent.succeeded","data":{"object":{"id":"pi_validation_failed","status":"succeeded"}}}';
        $headers = [
            'Stripe-Signature' => 't=1700000000,v1=invalid-signature',
            'Content-Type' => 'application/json',
        ];
        $queryParams = ['endpoint' => 'payments'];
        $bodyParams = ['ignored' => 'json-body-is-authoritative'];
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: $headers,
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            providerId: 'stripe',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookStripeProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookStripeValidator(
                signingSecret: 'whsec_validation_failure',
                currentTimestamp: 1700000000,
            )),
        );

        return [
            $processor,
            $input,
            'stripe_signature_mismatch',
            'Stripe webhook signature does not match the request payload.',
            null,
            $headers,
            $queryParams,
            $bodyParams,
        ];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, string, ?string, array<string, string|list<string>>, array<string, mixed>, array<string, mixed>}
     */
    private static function paypalMissingAuthenticityMarkerCase(): array
    {
        $rawBody = '{"id":"WH-VALIDATION-FAILED","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAPTURE-VALIDATION-FAILED","status":"COMPLETED"}}';
        $headers = [
            'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
            'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/validation-failed.pem',
            'PayPal-Auth-Algo' => 'SHA256withRSA',
            'PayPal-Transmission-Sig' => 'signature-validation-failed',
            'Content-Type' => 'application/json',
        ];
        $queryParams = ['endpoint' => 'payments'];
        $bodyParams = ['ignored' => 'json-body-is-authoritative'];
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: $headers,
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            providerId: 'paypal',
        );
        $verifier = new class implements WebhookPayPalSignatureVerifierInterface {
            public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
            {
                throw new \LogicException('PayPal signature verifier must not be called when authenticity markers are missing.');
            }
        };
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookPayPalProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookPayPalValidator($verifier, 'WH-CONFIGURED-VALIDATION-FAILED')),
        );

        return [
            $processor,
            $input,
            'paypal_required_transmission_header_missing',
            'Required PayPal transmission header "PayPal-Transmission-Id" is missing or empty.',
            null,
            $headers,
            $queryParams,
            $bodyParams,
        ];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, string, ?string, array<string, string|list<string>>, array<string, mixed>, array<string, mixed>}
     */
    private static function yookassaInvalidBasicAuthCase(): array
    {
        $rawBody = '{"type":"notification","event":"payment.succeeded","object":{"id":"payment-validation-failed","status":"succeeded"}}';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode('shop-id:wrong-secret-key'),
            'Content-Type' => 'application/json',
        ];
        $queryParams = ['endpoint' => 'payments'];
        $bodyParams = ['ignored' => 'json-body-is-authoritative'];
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: $headers,
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            providerId: 'yookassa',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookYooKassaProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookYooKassaValidator('shop-id', 'secret-key')),
        );

        return [
            $processor,
            $input,
            'yookassa_basic_auth_mismatch',
            'YooKassa Basic Auth credentials do not match the configured shop ID and secret key.',
            'payment.succeeded',
            $headers,
            $queryParams,
            $bodyParams,
        ];
    }

    /**
     * @return array{WebhookProcessor, WebhookInput, string, string, ?string, array<string, string|list<string>>, array<string, mixed>, array<string, mixed>}
     */
    private static function robokassaInvalidSignatureCase(): array
    {
        $rawBody = 'OutSum=100.00&InvId=123&SignatureValue=invalid-signature';
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $queryParams = ['endpoint' => 'payments'];
        $bodyParams = [
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => 'invalid-signature',
        ];
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: $headers,
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            providerId: 'robokassa',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookRobokassaProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookRobokassaValidator('password2')),
        );

        return [
            $processor,
            $input,
            'robokassa_signature_mismatch',
            'Robokassa callback signature does not match the request parameters.',
            null,
            $headers,
            $queryParams,
            $bodyParams,
        ];
    }
}
