<?php

declare(strict_types=1);

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Payments\Gateway\RobokassaGateway;
use Yiisoft\Payments\Model\Customer;
use Yiisoft\Payments\Model\PaymentIntent;
use Yiisoft\Payments\Model\PaymentMethod;
use Yiisoft\Payments\Model\PaymentMethodType;
use Yiisoft\Payments\Support\ArrayLogger;
use Yiisoft\Payments\Tests\Config\RobokassaConfigArray;
use Yiisoft\Payments\Tests\Config\RobokassaSandbox;

require __DIR__ . '/../vendor/autoload.php';

// Robokassa redirect does not use HTTP client, so you can use mocks here
/** @var ClientInterface $http */
$http = new class implements ClientInterface {
    public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        throw new \LogicException('RobokassaGateway does not send HTTP requests in this implementation.');
    }
};
/** @var RequestFactoryInterface $requestFactory */
$requestFactory = new class implements RequestFactoryInterface {
    public function createRequest(string $method, $uri): \Psr\Http\Message\RequestInterface
    {
        throw new \LogicException('Not used in RobokassaGateway.');
    }
};
/** @var StreamFactoryInterface $streamFactory */
$streamFactory = new class implements StreamFactoryInterface {
    public function createStream(string $content = ''): \Psr\Http\Message\StreamInterface
    {
        throw new \LogicException('Not used in RobokassaGateway.');
    }
    public function createStreamFromFile(string $filename, string $mode = 'r'): \Psr\Http\Message\StreamInterface
    {
        throw new \LogicException('Not used in RobokassaGateway.');
    }
    public function createStreamFromResource($resource): \Psr\Http\Message\StreamInterface
    {
        throw new \LogicException('Not used in RobokassaGateway.');
    }
};
/** @var LoggerInterface $logger */
$logger = new ArrayLogger();

$config = RobokassaConfigArray::asArray();

$customer = new Customer('c-sandbox', 'sandbox-buyer@example.com');
$intentId = 'order-sb-' . time();
$intent = new PaymentIntent(
    $intentId,
    $customer,
    RobokassaSandbox::AMOUNT,
    RobokassaSandbox::CURRENCY,
    'Robokassa sandbox CLI test'
);

$method = new PaymentMethod(PaymentMethodType::ROBOKASSA, 'card');

$gateway = new RobokassaGateway(
    $method,
    $config,
    $http,
    $requestFactory,
    $streamFactory,
    $logger
);

$result = $gateway->createPayment($intent);

if (!$result['success']) {
    echo "Failed to create Robokassa payment URL\n";
    var_dump($result['raw']);
    exit(1);
}

$redirectUrl = $result['redirect_url'] ?? null;
if ($redirectUrl === null) {
    echo "Redirect URL missing.\n";
    exit(1);
}

$stateFile = __DIR__ . '/../var/robokassa_state.json';
if (!is_dir(dirname($stateFile))) {
    mkdir(dirname($stateFile), 0777, true);
}
file_put_contents(
    $stateFile,
    json_encode([
        'inv_id' => $intentId,
        'amount' => RobokassaSandbox::AMOUNT,
        'currency' => RobokassaSandbox::CURRENCY,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "Robokassa payment URL created.\n";
echo "InvId: {$intentId}\n";
echo "URL: {$redirectUrl}\n";
echo "Open this URL in a browser and complete the test payment,\n";
echo "ensuring that your Result URL stores OutSum, InvId, SignatureValue\n";
echo "into var/robokassa_callback.json for the capture step.\n";
