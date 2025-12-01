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

$stateFile = __DIR__ . '/../var/paypal_state.json';
if (!is_file($stateFile)) {
    echo "State file not found. Run paypal_create.php first.\n";
    exit(1);
}

$state = json_decode((string)file_get_contents($stateFile), true);
if (!is_array($state) || empty($state['order_id']) || empty($state['intent_id'])) {
    echo "State file is invalid.\n";
    exit(1);
}

$orderId = (string)$state['order_id'];
$intentId = (string)$state['intent_id'];
$amount = (float)($state['amount'] ?? PaypalSandbox::AMOUNT);
$currency = (string)($state['currency'] ?? PaypalSandbox::CURRENCY);

$config = PaypalConfigArray::asArray();

$customer = new Customer('c-sandbox', 'sandbox-buyer@example.com');
$intent = new PaymentIntent(
    $intentId,
    $customer,
    $amount,
    $currency,
    'PayPal sandbox CLI test (capture)'
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

$result = $gateway->capture($intent, ['order_id' => $orderId]);

if (!$result['success']) {
    echo "Capture failed. Status: {$result['status']}\n";
    var_dump($result['raw']);
    exit(1);
}

$captureId = $intent->metadata['paypal_capture_id'] ?? null;

echo "Capture succeeded.\n";
echo "Status: {$result['status']}\n";
echo "Capture ID: " . ($captureId ?? '(not found)') . "\n";

$state['capture_id'] = $captureId;
file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "You can now run cli/paypal_refund.php to refund this capture.\n";
