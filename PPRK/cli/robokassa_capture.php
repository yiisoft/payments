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

// As in create step, HTTP client and factories are not used
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

$stateFile = __DIR__ . '/../var/robokassa_state.json';
$callbackFile = __DIR__ . '/../var/robokassa_callback.json';

if (!is_file($stateFile) || !is_file($callbackFile)) {
    echo "State or callback file not found.\n";
    exit(1);
}

$state = json_decode((string)file_get_contents($stateFile), true);
$callback = json_decode((string)file_get_contents($callbackFile), true);

if (!is_array($state) || !is_array($callback)) {
    echo "State or callback data invalid.\n";
    exit(1);
}

$invId = (string)($state['inv_id'] ?? '');
$amount = (float)($state['amount'] ?? RobokassaSandbox::AMOUNT);
$currency = (string)($state['currency'] ?? RobokassaSandbox::CURRENCY);

$customer = new Customer('c-sandbox', 'sandbox-buyer@example.com');
$intent = new PaymentIntent(
    $invId,
    $customer,
    $amount,
    $currency,
    'Robokassa sandbox CLI test (capture)'
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

$result = $gateway->capture($intent, $callback);

echo "Capture success: " . ($result['success'] ? 'yes' : 'no') . "\n";
echo "Status: {$result['status']}\n";
var_dump($result['raw']);
