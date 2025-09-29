<?php
declare(strict_types=1);

namespace Yiisoft\Tests\Integration;

use GuzzleHttp\Client as GuzzleClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Gateway\PayPalGateway;
use Yiisoft\Payments\Model\PaymentIntent;
use Yiisoft\Payments\Model\PurchaseUnit;
use Yiisoft\Payments\Model\Amount;
use Yiisoft\Payments\Model\Customer;
use Yiisoft\Payments\Model\PayerName;
use Yiisoft\Payments\Model\Phone;
use Yiisoft\Payments\Model\PhoneNumber;
use Yiisoft\Payments\Model\Address;
use Yiisoft\Payments\Model\ApplicationContext;
use Yiisoft\Payments\Tests\Config\PaypalSandbox;

/**
 * Integration test using only PayPalGateway public methods and previously implemented models.
 * Flow: create order -> get approval URL -> capture (using a pre-approved order id) -> refund -> retrieve.
 * Note: Approval is a browser/client step; this test uses a pre-approved order id from config to capture on server.
 */
final class PayPalCardPaymentTest extends TestCase
{
    private PayPalGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $httpClient = new GuzzleClient();
        $psr17      = new Psr17Factory();

        $this->gateway = new PayPalGateway(
            clientId: PaypalSandbox::CLIENT_ID,
            clientSecret: PaypalSandbox::CLIENT_SECRET,
            sandbox: PaypalSandbox::SANDBOX,
            httpClient: $httpClient,
            requestFactory: $psr17,
            streamFactory: $psr17
        );
    }

    public function testCreateConfirmCaptureRefundWithCard(): void
    {
        // 1) Build order request (all data from PaypalSandbox)
        $purchaseUnit = new PurchaseUnit(
            new Amount(PaypalSandbox::ORDER_CURRENCY_CODE, PaypalSandbox::ORDER_AMOUNT_VALUE)
        );

        $payerName = new PayerName(PaypalSandbox::PAYER_GIVEN_NAME, PaypalSandbox::PAYER_SURNAME);
        $customer = new Customer(
            name: $payerName,
            emailAddress: PaypalSandbox::PAYER_EMAIL ?: null,
            phone: PaypalSandbox::PAYER_PHONE_NATIONAL ? new Phone(new PhoneNumber(PaypalSandbox::PAYER_PHONE_NATIONAL)) : null,
            address: new Address(
                PaypalSandbox::ADDR_LINE1,
                PaypalSandbox::ADDR_CITY,
                PaypalSandbox::ADDR_POSTAL,
                PaypalSandbox::ADDR_COUNTRY,
                PaypalSandbox::ADDR_LINE2,
                PaypalSandbox::ADDR_STATE
            )
        );

        $appCtx = new ApplicationContext(
            returnUrl: PaypalSandbox::RETURN_URL,
            cancelUrl: PaypalSandbox::CANCEL_URL,
            brandName: PaypalSandbox::BRAND_NAME,
            landingPage: PaypalSandbox::LANDING_PAGE,
            userAction: PaypalSandbox::USER_ACTION,
            shippingPreference: PaypalSandbox::SHIPPING_PREFERENCE
        );

        $intent = new PaymentIntent(
            intent: PaypalSandbox::ORDER_INTENT,
            purchaseUnits: [$purchaseUnit],
            customer: $customer,
            applicationContext: $appCtx
        );

        // 2) Create order
        $created = $this->gateway->createPaymentIntent($intent);
        $this->assertNotEmpty($created->orderId, 'Order ID should be returned after creation');

        // 3) Get approval URL (buyer approval is a browser/client step)
        $approvalUrl = $this->gateway->getApprovalUrl($created->orderId ?? '');
        $this->assertNotEmpty($approvalUrl, 'Approval URL should be present for redirect-based approval');

        // 4) Capture a pre-approved order ID from config (approval happened outside this test)
        $approvedOrderId = PaypalSandbox::APPROVED_ORDER_ID ?? '';
        if (empty($approvedOrderId)) {
            $this->markTestSkipped('Set PaypalSandbox::APPROVED_ORDER_ID with an already approved order id to run capture/refund steps.');
        }

        $captured = $this->gateway->capturePaymentIntent($approvedOrderId);
        $this->assertNotEmpty($captured->status, 'Capture response should contain a status');
        $this->assertContains($captured->status, ['COMPLETED', 'APPROVED', 'PENDING'], 'Unexpected capture status');

        // 5) Refund a known capture ID from config
        $captureId = PaypalSandbox::CAPTURE_ID ?? '';
        if (empty($captureId)) {
            $this->markTestSkipped('Set PaypalSandbox::CAPTURE_ID from a previous capture to run the refund step.');
        }

        $ok = $this->gateway->createRefund($captureId, (float) PaypalSandbox::REFUND_AMOUNT_VALUE, PaypalSandbox::ORDER_CURRENCY_CODE);
        $this->assertTrue($ok, 'Refund should return true on success');

        // 6) Retrieve an order for verification (either provided or just created)
        $retrieveOrderId = PaypalSandbox::RETRIEVE_ORDER_ID ?: ($created->orderId ?? '');
        if (!empty($retrieveOrderId)) {
            $fetched = $this->gateway->retrievePaymentIntent($retrieveOrderId);
            $this->assertSame($retrieveOrderId, $fetched->orderId, 'Retrieved order id should match');
            $this->assertNotEmpty($fetched->status, 'Retrieved order should have a status');
        }
    }
}
