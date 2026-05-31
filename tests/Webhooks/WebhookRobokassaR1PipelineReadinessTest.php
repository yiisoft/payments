<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorRegistry;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookRobokassaValidator;

final class WebhookRobokassaR1PipelineReadinessTest extends TestCase
{
    public function testRobokassaR1PaymentWebhookPipelineIsReady(): void
    {
        $password2 = 'robokassa_r1_ready_password2';
        $outSum = '100.00';
        $invId = '123';
        $orderId = 'order-r1-ready';
        $signature = md5($outSum . ':' . $invId . ':' . $password2 . ':Shp_orderId=' . $orderId);
        $rawBody = http_build_query([
            'OutSum' => $outSum,
            'InvId' => $invId,
            'SignatureValue' => $signature,
            'Shp_orderId' => $orderId,
        ]);
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            queryParams: [
                'endpoint' => 'payments',
            ],
            bodyParams: [
                'OutSum' => $outSum,
                'InvId' => $invId,
                'SignatureValue' => $signature,
                'Shp_orderId' => $orderId,
            ],
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookRobokassaProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookRobokassaValidator($password2)),
        );

        $context = $processor->process($input);

        $this->assertSame(WebhookRobokassaCallbackFormat::PROVIDER_ID, $context->providerId);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
        $this->assertSame(WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL, $context->paymentStatus);
        $this->assertNull($context->validationFailureReason);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNull($context->unknownEventReason);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame($rawBody, $context->rawData->rawBody);
        $this->assertSame($input->headers, $context->rawData->headers);
        $this->assertSame($input->queryParams, $context->rawData->queryParams);
        $this->assertSame($input->bodyParams, $context->rawData->bodyParams);
        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $context->rawData->providerEventType);
        $this->assertSame('payments', $context->rawData->payload['endpoint'] ?? null);
        $this->assertSame($outSum, $context->rawData->payload['OutSum'] ?? null);
        $this->assertSame($invId, $context->rawData->payload['InvId'] ?? null);
        $this->assertSame($signature, $context->rawData->payload['SignatureValue'] ?? null);
        $this->assertSame($orderId, $context->rawData->payload['Shp_orderId'] ?? null);
    }
}
