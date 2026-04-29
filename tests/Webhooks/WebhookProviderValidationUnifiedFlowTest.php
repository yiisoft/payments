<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Tests\Webhooks\Support\SuccessfulWebhookProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorRegistry;
use Yiisoft\Payments\Webhooks\WebhookRobokassaValidator;

final class WebhookProviderValidationUnifiedFlowTest extends TestCase
{
    public function testValidRobokassaCallbackPassesProviderValidationAndContinuesUnifiedFlow(): void
    {
        $providerProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'robokassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'ResultURL',
            payload: ['InvId' => '123'],
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry($providerProcessor),
            new WebhookProviderValidatorRegistry(new WebhookRobokassaValidator('pass2')),
        );
        $input = new WebhookInput(
            rawBody: 'OutSum=100.00&InvId=123&SignatureValue=' . md5('100.00:123:pass2:Shp_order=abc'),
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
            ],
            bodyParams: [
                'SignatureValue' => md5('100.00:123:pass2:Shp_order=abc'),
                'Shp_order' => 'abc',
            ],
            providerId: 'robokassa',
        );

        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
        $this->assertNull($context->validationFailureReason);
        $this->assertSame($input, $context->rawInput);
        $this->assertSame(1, $providerProcessor->processCalls);
        $this->assertSame($input, $providerProcessor->processedInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame('OutSum=100.00&InvId=123&SignatureValue=' . md5('100.00:123:pass2:Shp_order=abc'), $context->rawData->rawBody);
        $this->assertSame(['Content-Type' => 'application/x-www-form-urlencoded'], $context->rawData->headers);
        $this->assertSame(['InvId' => '123'], $context->rawData->payload);
        $this->assertSame('ResultURL', $context->rawData->providerEventType);
    }

    public function testInvalidRobokassaSignatureReturnsValidationFailedContextBeforeProviderProcessing(): void
    {
        $providerProcessor = new SuccessfulWebhookProviderProcessor('robokassa');
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry($providerProcessor),
            new WebhookProviderValidatorRegistry(new WebhookRobokassaValidator('pass2')),
        );
        $input = new WebhookInput(
            rawBody: 'OutSum=100.00&InvId=123&SignatureValue=invalid-signature',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            queryParams: ['OutSum' => '100.00'],
            bodyParams: [
                'InvId' => '123',
                'SignatureValue' => 'invalid-signature',
            ],
            providerId: 'robokassa',
        );

        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame('robokassa_signature_mismatch', $context->validationFailureReason->code->value);
        $this->assertSame('Robokassa callback signature does not match the request parameters.', $context->validationFailureReason->message);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame('OutSum=100.00&InvId=123&SignatureValue=invalid-signature', $context->rawData->rawBody);
        $this->assertSame(['Content-Type' => 'application/x-www-form-urlencoded'], $context->rawData->headers);
        $this->assertSame(['OutSum' => '100.00'], $context->rawData->queryParams);
        $this->assertSame(['InvId' => '123', 'SignatureValue' => 'invalid-signature'], $context->rawData->bodyParams);
        $this->assertNull($context->rawData->payload);
        $this->assertNull($context->rawData->providerEventType);
        $this->assertSame(0, $providerProcessor->processCalls);
        $this->assertNull($providerProcessor->processedInput);
    }

    public function testMissingRobokassaRequiredParameterReturnsValidationFailedContextBeforeProviderProcessing(): void
    {
        $providerProcessor = new SuccessfulWebhookProviderProcessor('robokassa');
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry($providerProcessor),
            new WebhookProviderValidatorRegistry(new WebhookRobokassaValidator('pass2')),
        );
        $input = new WebhookInput(
            rawBody: 'OutSum=100.00&SignatureValue=' . md5('100.00:123:pass2'),
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            bodyParams: [
                'OutSum' => '100.00',
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
            providerId: 'robokassa',
        );

        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame('robokassa_required_parameter_missing', $context->validationFailureReason->code->value);
        $this->assertSame('Required Robokassa callback parameter "InvId" is missing or empty.', $context->validationFailureReason->message);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame('OutSum=100.00&SignatureValue=' . md5('100.00:123:pass2'), $context->rawData->rawBody);
        $this->assertSame(['Content-Type' => 'application/x-www-form-urlencoded'], $context->rawData->headers);
        $this->assertSame([], $context->rawData->queryParams);
        $this->assertSame([
            'OutSum' => '100.00',
            'SignatureValue' => md5('100.00:123:pass2'),
        ], $context->rawData->bodyParams);
        $this->assertNull($context->rawData->payload);
        $this->assertNull($context->rawData->providerEventType);
        $this->assertSame(0, $providerProcessor->processCalls);
        $this->assertNull($providerProcessor->processedInput);
    }
}
