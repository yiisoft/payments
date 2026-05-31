<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPaymentOutcomeRules;

final class WebhookPaymentOutcomeRulesTest extends TestCase
{
    /**
     * @return iterable<string, array{WebhookEventType}>
     */
    public static function processedR1PaymentOutcomeProvider(): iterable
    {
        yield 'created' => [WebhookEventType::PaymentCreated];
        yield 'processing' => [WebhookEventType::PaymentProcessing];
        yield 'requires action' => [WebhookEventType::PaymentRequiresAction];
        yield 'requires capture' => [WebhookEventType::PaymentRequiresCapture];
        yield 'succeeded' => [WebhookEventType::PaymentSucceeded];
        yield 'failed' => [WebhookEventType::PaymentFailed];
        yield 'canceled' => [WebhookEventType::PaymentCanceled];
    }

    #[DataProvider('processedR1PaymentOutcomeProvider')]
    public function testNonRefundRecognizedPaymentOutcomesAreProcessedInR1(WebhookEventType $eventType): void
    {
        $this->assertTrue(WebhookPaymentOutcomeRules::shouldProcess($eventType));
        $this->assertFalse(WebhookPaymentOutcomeRules::shouldRejectAsUnsupported($eventType));
    }

    public function testRefundLikeOutcomeIsRecognizedButUnsupportedInR1(): void
    {
        $this->assertFalse(WebhookPaymentOutcomeRules::shouldProcess(WebhookEventType::PaymentRefunded));
        $this->assertTrue(WebhookPaymentOutcomeRules::shouldRejectAsUnsupported(WebhookEventType::PaymentRefunded));
    }

    public function testProcessedPaymentOutcomesDoNotIncludeRefundNormalization(): void
    {
        $this->assertSame(
            [
                WebhookEventType::PaymentCreated,
                WebhookEventType::PaymentProcessing,
                WebhookEventType::PaymentRequiresAction,
                WebhookEventType::PaymentRequiresCapture,
                WebhookEventType::PaymentSucceeded,
                WebhookEventType::PaymentFailed,
                WebhookEventType::PaymentCanceled,
            ],
            WebhookPaymentOutcomeRules::processedPaymentOutcomes(),
        );
    }

    public function testUnsupportedPaymentOutcomesContainOnlyRefundLikeEventsForR1(): void
    {
        $this->assertSame(
            [WebhookEventType::PaymentRefunded],
            WebhookPaymentOutcomeRules::unsupportedPaymentOutcomes(),
        );
    }
}
