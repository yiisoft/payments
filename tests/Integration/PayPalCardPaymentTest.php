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
            phone: PaypalSandbox::PAYER_PHONE_NATIONAL
                ? new Phone(new PhoneNumber(PaypalSandbox::PAYER_PHONE_NATIONAL))
                : null,
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
    
        // 2) Create order and show approval URL for manual browser approval if needed
        $created = $this->gateway->createPaymentIntent($intent);
        $this->assertNotEmpty($created->orderId, 'Order ID should be returned after creation');
    
        $approvalUrl = $this->gateway->getApprovalUrl($created->orderId ?? '');
        $this->assertNotEmpty($approvalUrl, 'Approval URL should be present for redirect-based approval');
    
        $approvedOrderId = PaypalSandbox::APPROVED_ORDER_ID ?? '';
        if (empty($approvedOrderId)) {
            fwrite(STDOUT, PHP_EOL . '=== Manual approval required ===' . PHP_EOL);
            fwrite(STDOUT, 'Order ID:    ' . ($created->orderId ?? '') . PHP_EOL);
            fwrite(STDOUT, 'Approve URL: ' . $approvalUrl . PHP_EOL);
            fwrite(STDOUT, 'Open the URL in a browser and approve the order, then set PaypalSandbox::APPROVED_ORDER_ID and re-run this test.' . PHP_EOL . PHP_EOL);
            $this->markTestIncomplete('Waiting for manual browser approval; set APPROVED_ORDER_ID and re-run.');
        }
    
        // 3) Capture the pre-approved order
        $captured = $this->gateway->capturePaymentIntent($approvedOrderId);
        $this->assertNotEmpty($captured->status, 'Capture response should contain a status');
        $this->assertContains($captured->status, ['COMPLETED', 'APPROVED', 'PENDING'], 'Unexpected capture status');
    
        // 4) Determine CAPTURE_ID for refund: use config if set, otherwise print suggested ID and pause
        $captureId = PaypalSandbox::CAPTURE_ID ?? '';
        if (empty($captureId)) {
            // Assumes PayPalGateway populates PaymentIntent->captureIds from the capture response
            $suggested = $captured->captureIds[0] ?? null;
            if ($suggested) {
                fwrite(STDOUT, PHP_EOL . '=== Capture obtained ===' . PHP_EOL);
                fwrite(STDOUT, 'Suggested CAPTURE_ID: ' . $suggested . PHP_EOL);
                fwrite(STDOUT, 'Set PaypalSandbox::CAPTURE_ID to this value and re-run to execute refund in the same test.' . PHP_EOL . PHP_EOL);
                $this->markTestIncomplete('CAPTURE_ID not set; printed suggested ID from capture. Set it and re-run to test refund.');
            } else {
                $this->fail('No capture id found in capture response; cannot proceed to refund.');
            }
        }
    
        // 5) Refund (partial or full) using configured CAPTURE_ID
        $ok = $this->gateway->createRefund(
            $captureId,
            (float) PaypalSandbox::REFUND_AMOUNT_VALUE,
            PaypalSandbox::ORDER_CURRENCY_CODE
        );
        $this->assertTrue($ok, 'Refund should return true on success');
    
        // 6) Optional: retrieve an order for verification (config or the created one)
        $retrieveOrderId = PaypalSandbox::RETRIEVE_ORDER_ID ?: ($created->orderId ?? '');
        if (!empty($retrieveOrderId)) {
            $fetched = $this->gateway->retrievePaymentIntent($retrieveOrderId);
            $this->assertSame($retrieveOrderId, $fetched->orderId, 'Retrieved order id should match');
            $this->assertNotEmpty($fetched->status, 'Retrieved order should have a status');
        }
    }
    


}
