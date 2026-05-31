<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Yiisoft\Payments\Gateways\PayPalGateway;
use Yiisoft\Payments\Gateways\RobokassaGateway;
use Yiisoft\Payments\Gateways\StripeGateway;
use Yiisoft\Payments\Gateways\YooKassaGateway;
use Yiisoft\Payments\Tests\Support\TestHttpClient;
use Yiisoft\Payments\Webhooks\WebhookCapabilitiesProviderInterface;
use Yiisoft\Payments\Webhooks\WebhookCapability;
use Yiisoft\Payments\Webhooks\WebhookEntityKind;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPaymentOutcomeRules;
use Yiisoft\Payments\Webhooks\WebhookSupportStatus;

final class WebhookGatewayCapabilitiesTest extends TestCase
{
    public function testAllGatewaysDeclareWebhookCapabilities(): void
    {
        foreach ($this->createGateways() as $gateway) {
            $this->assertInstanceOf(WebhookCapabilitiesProviderInterface::class, $gateway);

            $capabilities = $gateway->getWebhookCapabilities();

            $this->assertGreaterThan(0, $capabilities->count());
            $this->assertContainsOnlyInstancesOf(WebhookCapability::class, $capabilities->all());
        }
    }

    public function testAllGatewaysDeclareR1PaymentRelatedScope(): void
    {
        foreach ($this->createGateways() as $gateway) {
            $capabilities = $gateway->getWebhookCapabilities()->all();

            $this->assertSame($this->expectedR1PaymentEventTypes(), array_map(
                static fn (WebhookCapability $capability): WebhookEventType => $capability->eventType,
                $capabilities,
            ));

            foreach ($capabilities as $capability) {
                $this->assertSame(WebhookEntityKind::Payment, $capability->entityKind);
            }
        }
    }

    public function testStripeWebhookCapabilitiesMatchImplementedR1PaymentMapping(): void
    {
        $psr17Factory = new Psr17Factory();
        $gateway = new StripeGateway(
            'test_api_key',
            new TestHttpClient($psr17Factory),
            $psr17Factory,
            $psr17Factory,
            new NullLogger(),
        );

        $this->assertSame([
            WebhookEventType::PaymentCreated->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentProcessing->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentRequiresAction->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentRequiresCapture->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentSucceeded->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentFailed->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentCanceled->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentRefunded->value => WebhookSupportStatus::Unsupported,
        ], $this->capabilitySupportStatuses($gateway));
    }

    public function testPayPalWebhookCapabilitiesMatchImplementedR1PaymentMapping(): void
    {
        $psr17Factory = new Psr17Factory();
        $gateway = new PayPalGateway(
            'test_client_id',
            'test_client_secret',
            true,
            new TestHttpClient($psr17Factory),
            $psr17Factory,
            $psr17Factory,
            new NullLogger(),
        );

        $this->assertSame([
            WebhookEventType::PaymentCreated->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentProcessing->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentRequiresAction->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentRequiresCapture->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentSucceeded->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentFailed->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentCanceled->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentRefunded->value => WebhookSupportStatus::Unsupported,
        ], $this->capabilitySupportStatuses($gateway));
    }

    public function testYooKassaWebhookCapabilitiesMatchImplementedR1PaymentMapping(): void
    {
        $psr17Factory = new Psr17Factory();
        $gateway = new YooKassaGateway(
            'test_shop_id',
            'test_secret_key',
            new TestHttpClient($psr17Factory),
            $psr17Factory,
            $psr17Factory,
            new NullLogger(),
        );

        $this->assertSame([
            WebhookEventType::PaymentCreated->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentProcessing->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentRequiresAction->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentRequiresCapture->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentSucceeded->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentFailed->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentCanceled->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentRefunded->value => WebhookSupportStatus::Unsupported,
        ], $this->capabilitySupportStatuses($gateway));
    }

    public function testGatewayCapabilityDeclarationsMatchR1PaymentOutcomeScope(): void
    {
        $expectedEventTypes = [
            ...WebhookPaymentOutcomeRules::processedPaymentOutcomes(),
            ...WebhookPaymentOutcomeRules::unsupportedPaymentOutcomes(),
        ];

        foreach ($this->createGateways() as $gateway) {
            $capabilities = $gateway->getWebhookCapabilities()->all();
            $actualEventTypes = array_map(
                static fn (WebhookCapability $capability): WebhookEventType => $capability->eventType,
                $capabilities,
            );

            $this->assertSame($expectedEventTypes, $actualEventTypes);
            $this->assertSameSize(array_unique($actualEventTypes, SORT_REGULAR), $actualEventTypes);

            foreach ($capabilities as $capability) {
                $this->assertSame(WebhookEntityKind::Payment, $capability->entityKind);
            }
        }
    }

    public function testSupportedGatewayCapabilitiesStayWithinR1ProcessedPaymentOutcomeScope(): void
    {
        $processedOutcomeValues = array_map(
            static fn (WebhookEventType $eventType): string => $eventType->value,
            WebhookPaymentOutcomeRules::processedPaymentOutcomes(),
        );
        $unsupportedOutcomeValues = array_map(
            static fn (WebhookEventType $eventType): string => $eventType->value,
            WebhookPaymentOutcomeRules::unsupportedPaymentOutcomes(),
        );

        foreach ($this->createGateways() as $gateway) {
            foreach ($gateway->getWebhookCapabilities() as $capability) {
                if ($capability->supportStatus === WebhookSupportStatus::Supported) {
                    $this->assertContains($capability->eventType->value, $processedOutcomeValues);
                    $this->assertNotContains($capability->eventType->value, $unsupportedOutcomeValues);

                    continue;
                }

                $this->assertSame(WebhookSupportStatus::Unsupported, $capability->supportStatus);
            }
        }
    }

    public function testPaymentRefundedStaysUnsupportedInAllR1CapabilityDeclarations(): void
    {
        foreach ($this->createGateways() as $gateway) {
            $refundCapabilities = array_values(array_filter(
                $gateway->getWebhookCapabilities()->all(),
                static fn (WebhookCapability $capability): bool => $capability->eventType === WebhookEventType::PaymentRefunded,
            ));

            $this->assertCount(1, $refundCapabilities);
            $this->assertSame(WebhookEntityKind::Payment, $refundCapabilities[0]->entityKind);
            $this->assertSame(WebhookSupportStatus::Unsupported, $refundCapabilities[0]->supportStatus);
        }
    }

    public function testRobokassaWebhookCapabilitiesMatchImplementedR1PaymentMapping(): void
    {
        $psr17Factory = new Psr17Factory();
        $gateway = new RobokassaGateway(
            'test_merchant_login',
            'test_password1',
            'test_password2',
            'test_password3',
            true,
            new TestHttpClient($psr17Factory),
            $psr17Factory,
            $psr17Factory,
            new NullLogger(),
        );

        $this->assertSame([
            WebhookEventType::PaymentCreated->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentProcessing->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentRequiresAction->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentRequiresCapture->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentSucceeded->value => WebhookSupportStatus::Supported,
            WebhookEventType::PaymentFailed->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentCanceled->value => WebhookSupportStatus::Unsupported,
            WebhookEventType::PaymentRefunded->value => WebhookSupportStatus::Unsupported,
        ], $this->capabilitySupportStatuses($gateway));
    }

    /**
     * @return list<WebhookCapabilitiesProviderInterface>
     */
    private function createGateways(): array
    {
        $psr17Factory = new Psr17Factory();
        $httpClient = new TestHttpClient($psr17Factory);
        $logger = new NullLogger();

        return [
            new StripeGateway(
                'test_api_key',
                $httpClient,
                $psr17Factory,
                $psr17Factory,
                $logger,
            ),
            new PayPalGateway(
                'test_client_id',
                'test_client_secret',
                true,
                $httpClient,
                $psr17Factory,
                $psr17Factory,
                $logger,
            ),
            new YooKassaGateway(
                'test_shop_id',
                'test_secret_key',
                $httpClient,
                $psr17Factory,
                $psr17Factory,
                $logger,
            ),
            new RobokassaGateway(
                'test_merchant_login',
                'test_password1',
                'test_password2',
                'test_password3',
                true,
                $httpClient,
                $psr17Factory,
                $psr17Factory,
                $logger,
            ),
        ];
    }

    /**
     * @return array<string, WebhookSupportStatus>
     */
    private function capabilitySupportStatuses(WebhookCapabilitiesProviderInterface $gateway): array
    {
        $supportStatuses = [];

        foreach ($gateway->getWebhookCapabilities() as $capability) {
            $supportStatuses[$capability->eventType->value] = $capability->supportStatus;
        }

        return $supportStatuses;
    }

    /**
     * @return list<WebhookEventType>
     */
    private function expectedR1PaymentEventTypes(): array
    {
        return [
            WebhookEventType::PaymentCreated,
            WebhookEventType::PaymentProcessing,
            WebhookEventType::PaymentRequiresAction,
            WebhookEventType::PaymentRequiresCapture,
            WebhookEventType::PaymentSucceeded,
            WebhookEventType::PaymentFailed,
            WebhookEventType::PaymentCanceled,
            WebhookEventType::PaymentRefunded,
        ];
    }
}
