<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventRecognizerInterface;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalEventRecognizer;

final class WebhookPayPalEventRecognizerTest extends TestCase
{
    public function testImplementsEventRecognizerContract(): void
    {
        $recognizer = new WebhookPayPalEventRecognizer();

        $this->assertInstanceOf(WebhookEventRecognizerInterface::class, $recognizer);
    }

    public function testDoesNotRecognizeProviderEventTypeYet(): void
    {
        $recognizer = new WebhookPayPalEventRecognizer();
        $input = new WebhookInput(rawBody: '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}');

        $this->assertNull($recognizer->recognizeProviderEventType($input));
    }

    public function testDoesNotNormalizeProviderEventTypeYet(): void
    {
        $recognizer = new WebhookPayPalEventRecognizer();

        $this->assertNull($recognizer->recognizeEventType('PAYMENT.CAPTURE.COMPLETED'));
    }
}
