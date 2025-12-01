<?php

declare(strict_types=1);

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Payments\Gateway\PayPalGateway;
use Yiisoft\Payments\Model\Customer;
use Yiisoft\Payments\Model\PaymentIntent;
use Yiisoft\Payments\Model\PaymentMethod;
use Yiisoft\Payments\Model\PaymentMethodType;
use Yiisoft\Payments\Support\ArrayLogger;
use Yiisoft\Payments\Tests\Config\PaypalConfigArray;
use Yiisoft\Payments\Tests\Config\PaypalSandbox;

use GuzzleHttp\Client as GuzzleClient;
use Nyholm\Psr7\Factory\Psr17Factory;

require __DIR__ . '/../vendor/autoload.php';

// Replace these with your real PSR implementations
/** @var ClientInterface $http */
$http = new GuzzleClient();
/** @var RequestFactoryInterface $requestFactory */
$requestFactory = new Psr17Factory();
/** @var StreamFactoryInterface $streamFactory */
$streamFactory = new Psr17Factory();
/** @var LoggerInterface $logger */
$logger = new ArrayLogger();

$config = PaypalConfigArray::asArray();

$customer = new Customer('c-sandbox', 'sandbox-buyer@example.com');
$intentId = 'order-sb-' . time();
$intent = new PaymentIntent(
    $intentId,
    $customer,
    PaypalSandbox::AMOUNT,
    PaypalSandbox::CURRENCY,
    'PayPal sandbox CLI test'
);

$method = new PaymentMethod(PaymentMethodType::PAYPAL, 'paypal_wallet');

$gateway = new PayPalGateway(
    $method,
    $config,
    $http,
    $requestFactory,
    $streamFactory,
    $logger
);

$result = $gateway->createPayment($intent);

if (!$result['success']) {
    echo "Failed to create PayPal order\n";
    var_dump($result['raw']);
    exit(1);
}

$orderId = $result['raw']['id'] ?? null;
$approvalUrl = $result['redirect_url'] ?? null;

if ($orderId === null || $approvalUrl === null) {
    echo "Order ID or approval URL missing in PayPal response\n";
    exit(1);
}

// Store state for next step
$stateFile = __DIR__ . '/../var/paypal_state.json';
if (!is_dir(dirname($stateFile))) {
    mkdir(dirname($stateFile), 0777, true);
}
file_put_contents(
    $stateFile,
    json_encode([
        'order_id' => $orderId,
        'intent_id' => $intentId,
        'amount' => PaypalSandbox::AMOUNT,
        'currency' => PaypalSandbox::CURRENCY,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "Order created.\n";
echo "Order ID: {$orderId}\n";
echo "Approval URL: {$approvalUrl}\n";
echo "Open this URL in a browser and approve the payment with a sandbox buyer,\n";
echo "then run cli/paypal_capture.php\n";
