<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventRecognizerInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaEventRecognizer;

final class WebhookRobokassaEventRecognizerTest extends TestCase
{
    public function testImplementsEventRecognizerContract(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $this->assertInstanceOf(WebhookEventRecognizerInterface::class, $recognizer);
    }

    public function testRecognizesSupportedResultUrlCallbackFromQueryParams(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $providerEventType = $recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '', queryParams: [
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => 'abc123',
        ]));

        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $providerEventType);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $recognizer->recognizeEventType($providerEventType));
    }

    public function testRecognizesSupportedResultUrlCallbackFromBodyParams(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $providerEventType = $recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '', bodyParams: [
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => 'abc123',
        ]));

        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $providerEventType);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $recognizer->recognizeEventType($providerEventType));
    }

    public function testRecognizesSupportedCallbackWhenRequiredParamsAreDuplicatedWithSameValues(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $providerEventType = $recognizer->recognizeProviderEventType(new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'abc123',
            ],
            bodyParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'abc123',
            ],
        ));

        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $providerEventType);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $recognizer->recognizeEventType($providerEventType));
    }

    public function testRecognizesSupportedCallbackWithCustomProviderFields(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $providerEventType = $recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '', queryParams: [
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => 'abc123',
            'Shp_orderId' => 'order-123',
        ]));

        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $providerEventType);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $recognizer->recognizeEventType($providerEventType));
    }

    public function testReturnsNullForInputWithoutCallbackParams(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(
            rawBody: '{"OutSum":"100.00","InvId":"123","SignatureValue":"abc123"}',
            headers: ['Content-Type' => 'application/json'],
        )));
    }

    public function testReturnsNullForMissingRequiredCallbackParameter(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '', queryParams: [
            'OutSum' => '100.00',
            'SignatureValue' => 'abc123',
        ])));
    }

    public function testReturnsNullForEmptyRequiredCallbackParameter(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '', queryParams: [
            'OutSum' => '100.00',
            'InvId' => ' ',
            'SignatureValue' => 'abc123',
        ])));
    }

    public function testReturnsNullForNonStringRequiredCallbackParameter(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(rawBody: '', queryParams: [
            'OutSum' => '100.00',
            'InvId' => 123,
            'SignatureValue' => 'abc123',
        ])));
    }

    public function testReturnsNullForConflictingRequiredCallbackParameter(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $this->assertNull($recognizer->recognizeProviderEventType(new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'abc123',
            ],
            bodyParams: [
                'OutSum' => '200.00',
            ],
        )));
    }

    public function testReturnsNullForUnsupportedProviderEventType(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $this->assertNull($recognizer->recognizeEventType('unsupported_callback'));
    }

}
