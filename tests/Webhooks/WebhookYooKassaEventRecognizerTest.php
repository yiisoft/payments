<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventRecognizerInterface;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookYooKassaEventRecognizer;

final class WebhookYooKassaEventRecognizerTest extends TestCase
{
    public function testImplementsEventRecognizerContract(): void
    {
        $recognizer = new WebhookYooKassaEventRecognizer();

        $this->assertInstanceOf(WebhookEventRecognizerInterface::class, $recognizer);
    }

    public function testReturnsNullBeforeProviderEventRecognitionIsConfigured(): void
    {
        $recognizer = new WebhookYooKassaEventRecognizer();
        $input = new WebhookInput(rawBody: '{"event":"payment.succeeded"}');

        $this->assertNull($recognizer->recognizeProviderEventType($input));
        $this->assertNull($recognizer->recognizeEventType('payment.succeeded'));
    }
}
