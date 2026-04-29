<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px" alt="Yii">
    </a>
    <h1 align="center">Yii Payment Gateway</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/payments/v)](https://packagist.org/packages/yiisoft/payments)
[![Total Downloads](https://poser.pugx.org/yiisoft/payments/downloads)](https://packagist.org/packages/yiisoft/payments)
[![Build status](https://github.com/yiisoft/payments/actions/workflows/build.yml/badge.svg?branch=master)](https://github.com/yiisoft/payments/actions/workflows/build.yml?query=branch%3Ama)
[![Code Coverage](https://codecov.io/gh/yiisoft/payments/branch/master/graph/badge.svg)](https://codecov.io/gh/yiisoft/payments)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fpayments%2Fmaster)](https://dashboard.stryker)
[![Static analysis](https://github.com/yiisoft/payments/actions/workflows/static.yml/badge.svg?branch=master)](https://github.com/yiisoft/payments/actions/workflows/static.yml?query=branch)
[![type-coverage](https://shepherd.dev/github/yiisoft/payments/coverage.svg)](https://shepherd.dev/github/yiisoft/payments)
[![psalm-level](https://shepherd.dev/github/yiisoft/payments/level.svg)](https://shepherd.dev/github/yiisoft/payments)

A modern PHP 8.1+ library providing a unified interface for multiple payment gateways, with support for Stripe, PayPal (REST API v2), Robokassa and YooKassa.

## Requirements

- PHP 8.1 or higher.

## Installation

The package could be installed with [Composer](https://getcomposer.org):

```shell
composer require yiisoft/payments
```

## How it Works

```mermaid
%%{init: {"theme":"base","themeVariables": {
  "background":"transparent",
  "fontFamily":"ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif",
  "primaryColor":"#0f172a",
  "primaryTextColor":"#e2e8f0",
  "primaryBorderColor":"#94a3b8",
  "lineColor":"#94a3b8",
  "secondaryColor":"#052e16",
  "tertiaryColor":"#1e293b",
  "clusterBkg":"#0b1220",
  "clusterBorder":"#334155"
}}}%%
graph TD
    A[Application] -->|Uses| B[PaymentGatewayInterface]
    B -->|Implemented by| C[StripeGateway]
    B -->|Implemented by| D[PayPalGateway]
    B -->|Implemented by| E[RobokassaGateway]
    B -->|Implemented by| F[YooKassaGateway]
    B -->|Can be extended to| G[CustomGateway]

    subgraph "Core Components"
        H[Customer] -->|Used by| B
        I[PaymentIntent] -->|Used by| B
        J[PaymentMethod] -->|Used by| B
        K[PaymentException] -->|Thrown by| B
    end

    subgraph "Gateway Implementations"
        C -->|Uses| L[Stripe API]
        D -->|Uses| M[PayPal REST API v2]
        E -->|Uses| N[Robokassa API]
        F -->|Uses| O[YooKassa API]
    end

    %% High-contrast styling that stays readable on GitHub dark theme
    classDef app fill:#111827,stroke:#cbd5e1,color:#f8fafc;
    classDef iface fill:#1e293b,stroke:#38bdf8,color:#f8fafc;
    classDef gateway fill:#0f172a,stroke:#60a5fa,color:#e2e8f0;
    classDef model fill:#0b1220,stroke:#94a3b8,color:#e2e8f0;
    classDef ex fill:#3b0a0a,stroke:#fb7185,color:#ffe4e6;
    classDef api fill:#052e16,stroke:#34d399,color:#ecfdf5;

    class A app;
    class B iface;
    class C,D,E,F,G gateway;
    class H,I,J model;
    class K ex;
    class L,M,N,O api;
```

The library provides a unified interface for multiple payment gateways, with each gateway implementing the `PaymentGatewayInterface`. The main components are:

- **PaymentGatewayInterface**: Defines the common API for all payment gateways
- **AbstractGateway**: Base class with shared functionality
- **Gateway-specific implementations**: `StripeGateway`, `PayPalGateway`, `RobokassaGateway`, `YooKassaGateway`
- **Data Models**: `Customer`, `PaymentIntent`, `PaymentMethod` for type-safe operations

## Features

- **Unified API** - Single interface for multiple payment providers
- **Type Safety** - Strictly typed models and responses
- **PSR Standards** - Follows PSR-4, PSR-7, PSR-17, and PSR-18
- **Extensible** - Easy to add new payment gateways
- **Modern PHP** - Requires PHP 8.1+ with strict types and readonly properties

## Payment Flow

### 1. Core Concepts

#### Customer
Represents a customer in the payment system. Contains:
- `id`: Unique identifier in the payment system
- `email`: Customer's email address
- `name`: Customer's full name
- `metadata`: Additional custom data

```php
$customer = new Customer(
    id: 'cus_123', // null for new customers
    email: 'customer@example.com',
    name: 'John Doe',
    metadata: ['user_id' => 42]
);
```

#### Payment Method
Represents how a customer will pay (credit card, PayPal, etc.). Contains:
- `id`: Unique identifier
- `type`: Payment method type (e.g., 'card', 'paypal')
- `details`: Payment method specific data (last4, brand, etc.)
- `customerId`: Reference to the customer
- `billingDetails`: Billing details (name, email, address, etc.)

```php
use Yiisoft\Payments\Models\PaymentMethod;
use Yiisoft\Payments\Models\PaymentMethodType;

$paymentMethod = new PaymentMethod(
    id: 'pm_123',
    type: PaymentMethodType::CARD,
    details: [
        'last4' => '4242',
        'brand' => 'visa',
        'exp_month' => 12,
        'exp_year' => 2025,
    ],
    customerId: 'cus_123',
    billingDetails: [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'address' => [
            'line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'postal_code' => '94105',
            'country' => 'US',
        ],
    ],
);

// Available payment method types:
// - PaymentMethodType::CARD
// - PaymentMethodType::PAYPAL
// - PaymentMethodType::SEPA_DEBIT

// Check if a payment method type is valid
$isValid = PaymentMethodType::isValid('card'); // true

// Get all available payment method types
$allTypes = PaymentMethodType::all();
```

#### Payment Intent
Represents a single payment transaction. Contains:
- `id`: Unique identifier
- `amount`: Amount in smallest currency unit (e.g., cents)
- `currency`: 3-letter ISO currency code
- `status`: Current status (e.g., 'requires_payment_method', 'succeeded')
- `customerId`: Reference to the customer
- `paymentMethodId`: Reference to the payment method
- `metadata`: Additional custom data

```php
$intent = new PaymentIntent(
    id: 'pi_123', // null for new intents
    amount: 1000, // $10.00
    currency: 'usd',
    status: 'requires_payment_method',
    customerId: 'cus_123',
    paymentMethodId: 'pm_123',
    metadata: ['order_id' => 'abc123']
);
```

### 2. Payment Flow Steps

#### Step 1: Initialize the Gateway
```php
$gateway = new StripeGateway(
    apiKey: 'your_stripe_key',
    httpClient: $httpClient,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory
);
```

#### Custom API endpoints

Each gateway has a small endpoints value object that allows overriding vendor base URLs (useful for stubs, proxies or alternative environments).

```php
use Yiisoft\Payments\Endpoints\StripeEndpoints;
use Yiisoft\Payments\Endpoints\PayPalEndpoints;
use Yiisoft\Payments\Endpoints\RobokassaEndpoints;
use Yiisoft\Payments\Endpoints\YooKassaEndpoints;

$stripe = new StripeGateway(
    apiKey: 'your_stripe_key',
    httpClient: $httpClient,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory,
    endpoints: new StripeEndpoints(baseUri: 'https://proxy.example/stripe/v1'),
);

$paypal = new PayPalGateway(
    clientId: 'your_client_id',
    clientSecret: 'your_client_secret',
    sandbox: true,
    httpClient: $httpClient,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory,
    endpoints: new PayPalEndpoints(
        sandboxBaseUri: 'https://api-m.sandbox.paypal.com',
        liveBaseUri: 'https://api-m.paypal.com',
    ),
);

$robokassa = new RobokassaGateway(
    merchantLogin: 'demo',
    password1: 'pass1',
    password2: 'pass2',
    password3: 'pass3',
    testMode: true,
    httpClient: $httpClient,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory,
    endpoints: new RobokassaEndpoints(
        invoiceApiBaseUri: 'https://services.robokassa.ru/InvoiceServiceWebApi/api',
        refundApiBaseUri: 'https://services.robokassa.ru/RefundService/Refund',
        xmlApiBaseUri: 'https://auth.robokassa.ru/Merchant/WebService/Service.asmx',
    ),
);

$yookassa = new YooKassaGateway(
    shopId: 'your_shop_id',
    secretKey: 'your_secret_key',
    httpClient: $httpClient,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory,
    endpoints: new YooKassaEndpoints(baseUri: 'https://api.yookassa.ru/v3'),
);
```

#### Step 2: Create or Retrieve Customer
```php
// Create new customer
$customer = $gateway->createCustomer(new Customer(
    email: 'customer@example.com',
    name: 'John Doe'
));

// Or retrieve existing customer
$customer = $gateway->retrieveCustomer('cus_existing123');
```

#### Step 3: Collect Payment Method (Frontend)
```javascript
// Example using Stripe.js
const { paymentMethod, error } = await stripe.createPaymentMethod({
  type: 'card',
  card: elements.getElement(CardElement)
});

// Send paymentMethod.id to your server
```

#### Step 4: Attach Payment Method (Server-Side)
```php
// If your frontend already created a payment method (e.g. via Stripe.js),
// you can simply attach it to the customer:
$paymentMethod = $gateway->attachPaymentMethod(
    $_POST['payment_method_id'],
    $customer->id,
);
```

#### Step 5: Create Payment Intent
```php
$intent = $gateway->createPaymentIntent(new PaymentIntent(
    amount: 1000, // $10.00
    currency: 'usd',
    customerId: $customer->id,
    paymentMethodId: $paymentMethod->id,
    metadata: ['order_id' => 'abc123']
));
```

#### Step 6: Confirm Payment (Client-Side)
```javascript
const { error, paymentIntent } = await stripe.confirmCardPayment(
  '{{ $intent->clientSecret }}',
  {
    payment_method: '{{ $paymentMethod->id }}',
    receipt_email: 'customer@example.com',
  }
);

if (error) {
  // Handle error
} else if (paymentIntent.status === 'succeeded') {
  // Payment succeeded!
}
```

#### Step 7: Webhooks (optional)
This library does not include webhook signature verification or event parsing.
Handle webhooks in your application using the provider's official documentation/SDK.


### 3. Handling Different Statuses

Payment Intents can have these statuses:
- `requires_payment_method`: Customer needs to add a payment method
- `requires_confirmation`: Payment needs to be confirmed
- `requires_action`: Customer needs to complete additional actions (3D Secure, etc.)
- `processing`: Payment is being processed
- `requires_capture`: Payment is authorized and needs to be captured
- `canceled`: Payment was canceled
- `succeeded`: Payment was successful

### 4. Refunds

```php
$refund = $gateway->createRefund('pi_123', [
    'amount' => 1000, // Optional: partial refund
    'reason' => 'requested_by_customer'
]);
```

### 5. Error Handling

Always wrap payment operations in try-catch blocks:

```php
try {
    $intent = $gateway->createPaymentIntent($paymentIntent);
} catch (PaymentException $e) {
    // Handle specific error types
    switch ($e->errorCode) {
        case 'card_declined':
            // Handle card decline
            break;
        case 'insufficient_funds':
            // Handle insufficient funds
            break;
        default:
            // Handle other errors
    }
}
```

## Usage

### Initializing a Gateway

The library relies on PSR-18 (HTTP client) and PSR-17 (request/stream factories).
In the examples below we use `symfony/http-client` and `nyholm/psr7`, but you can use any compatible implementations.

#### Stripe

```php
use Yiisoft\Payments\Gateways\StripeGateway;
use Symfony\Component\HttpClient\Psr18Client;
use Nyholm\Psr7\Factory\Psr17Factory;

$httpClient = new Psr18Client();      // Any PSR-18 client will work
$psr17Factory = new Psr17Factory();   // PSR-17 factories (request + stream)

$stripe = new StripeGateway(
    apiKey: 'YOUR_STRIPE_SECRET_KEY',
    httpClient: $httpClient,
    requestFactory: $psr17Factory,
    streamFactory: $psr17Factory,
);
```

#### PayPal (Checkout Orders API v2)

```php
use Yiisoft\Payments\Gateways\PayPalGateway;

// Reuse $httpClient and $psr17Factory from the Stripe example above (or provide your own PSR-18/PSR-17 implementations).
$paypal = new PayPalGateway(
    clientId: 'YOUR_CLIENT_ID',
    clientSecret: 'YOUR_CLIENT_SECRET',
    sandbox: true,
    httpClient: $httpClient,
    requestFactory: $psr17Factory,
    streamFactory: $psr17Factory,
);
```

#### Robokassa

```php
use Yiisoft\Payments\Gateways\RobokassaGateway;

// Reuse $httpClient and $psr17Factory from the Stripe example above (or provide your own PSR-18/PSR-17 implementations).
$robokassa = new RobokassaGateway(
    merchantLogin: 'YOUR_MERCHANT_LOGIN',
    password1: 'YOUR_PASSWORD_1', // Invoice API (JWT signing)
    password2: 'YOUR_PASSWORD_2', // XML status API (OpStateExt)
    password3: 'YOUR_PASSWORD_3', // Refund API v2 (JWT signing). Set to null if you don't need refunds.
    testMode: true,
    httpClient: $httpClient,
    requestFactory: $psr17Factory,
    streamFactory: $psr17Factory,
);
```



### Working with Customers

```php
use Yiisoft\Payments\Models\Customer;

// Create a customer
$customer = $gateway->createCustomer(new Customer(
    email: 'customer@example.com',
    name: 'John Doe',
    metadata: ['user_id' => 42],
));

// Retrieve a customer
$customer = $gateway->retrieveCustomer($customer->id);

// Update a customer (models are readonly, create a new instance)
$customer = $gateway->updateCustomer(new Customer(
    id: $customer->id,
    email: 'new.email@example.com',
    name: $customer->name,
    phone: $customer->phone,
    address: $customer->address,
    metadata: $customer->metadata,
    description: $customer->description,
));

// Delete a customer
$gateway->deleteCustomer($customer->id);
```

### Working with Payment Methods

```php
use Yiisoft\Payments\Models\PaymentMethod;
use Yiisoft\Payments\Models\PaymentMethodType;

// Note: payment method payload is gateway-specific.
// For card payments you should avoid handling raw card data on your server.
// Use provider tokenization (e.g. Stripe.js) whenever possible.

$paymentMethod = $gateway->createPaymentMethod(new PaymentMethod(
    type: PaymentMethodType::CARD,
    details: [
        // Example (Stripe): pass a token created on the client side.
        // The gateway will send it under the "card" key because type === "card".
        'token' => 'tok_visa',
    ],
    customerId: $customer->id,
));

$paymentMethod = $gateway->attachPaymentMethod($paymentMethod->id, $customer->id);
```

### Processing Payments

```php
use Yiisoft\Payments\Models\PaymentIntent;

// Create a payment intent / order / invoice (gateway-specific)
$intent = $gateway->createPaymentIntent(new PaymentIntent(
    amount: 1000,          // in the smallest currency unit (e.g. cents)
    currency: 'USD',
    customerId: $customer->id,
    paymentMethodId: $paymentMethod->id,
    description: 'Order #123',
    metadata: ['order_id' => '123'],
));

// Some gateways (PayPal, Robokassa) require a customer approval step via redirect URL:
$redirectUrl = $intent->nextAction['redirect_to_url']['url'] ?? null;

// Capture the payment (only for gateways/flows that support delayed capture)
if ($intent->status === PaymentIntent::STATUS_REQUIRES_CAPTURE) {
    $intent = $gateway->capturePaymentIntent($intent->id);
}

// Refund
$refund = $gateway->createRefund($intent->id, [
    'amount' => 1000, // optional partial refund
    'reason' => 'requested_by_customer',
]);
```

## Available Gateways

### Stripe (`StripeGateway`)

- Customers
- Payment Methods (create + attach)
- Payment Intents (create / retrieve / confirm / capture / cancel)
- Refunds

> Webhook verification/event parsing is intentionally out of scope for this library. Implement it in your application using Stripe docs/SDK.

### PayPal (`PayPalGateway`) — REST API v2 (Checkout Orders)

- Payment Intents are mapped to PayPal **Orders** (`/v2/checkout/orders`)
- `createPaymentIntent()` creates an order and may return an approval URL in `PaymentIntent::$nextAction['redirect_to_url']['url']`
- `capturePaymentIntent()` captures an order (`/v2/checkout/orders/{id}/capture`)
- `createRefund()` refunds a capture (`/v2/payments/captures/{capture_id}/refund`)

> PayPal does not expose generic Customer/PaymentMethod resources compatible with the library's models, so `Customer` / `PaymentMethod` operations are treated as lightweight placeholders (no persistent “vault” is created).

### Robokassa (`RobokassaGateway`)

- Payment Intents are mapped to Robokassa **invoices** (Invoice API JWT)
- `createPaymentIntent()` creates an invoice and returns a redirect URL in `PaymentIntent::$nextAction['redirect_to_url']['url']`
- `retrievePaymentIntent()` checks invoice status via **OpStateExt**
- `createRefund()` performs refund via **Refund API v2** (JWT)

> Robokassa customer/payment-method concepts differ from card processors, so `Customer` / `PaymentMethod` operations are implemented as placeholders for interface compatibility.

## Extending with New Gateways

To add a new payment gateway, create a class that implements `PaymentGatewayInterface`.
For convenience you can extend `Yiisoft\Payments\Gateways\AbstractGateway`, which provides:

- JSON request/response handling (PSR-18 + PSR-17)
- basic error-to-exception mapping (`PaymentException`, `InvalidRequestException`)
- a helper to build requests: `createRequest()`
- a helper to send and decode responses: `sendRequest()`

Example (minimal skeleton):

```php
<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use Yiisoft\Payments\Gateways\AbstractGateway;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class AcmePayGateway extends AbstractGateway
{
    public function __construct(
        private string $apiKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ) {
        parent::__construct($httpClient, $requestFactory, $streamFactory);
    }

    protected function getBaseUri(): string
    {
        return 'https://api.acmepay.com/v1';
    }

    public function createCustomer(Customer $customer): Customer
    {
        $response = $this->sendRequest(
            $this->createRequest('POST', '/customers', [
                'email' => $customer->email,
                'name' => $customer->name,
            ])
        );

        return Customer::fromArray($response);
    }

    public function createPaymentIntent(PaymentIntent $intent): PaymentIntent
    {
        $response = $this->sendRequest(
            $this->createRequest('POST', '/payment_intents', [
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'metadata' => $intent->metadata,
            ])
        );

        return PaymentIntent::fromArray($response);
    }

    // Implement the remaining methods from PaymentGatewayInterface...
    public function retrieveCustomer(string $customerId): Customer { /* ... */ }
    public function updateCustomer(Customer $customer): Customer { /* ... */ }
    public function deleteCustomer(string $customerId): void { /* ... */ }
    public function createPaymentMethod(PaymentMethod $paymentMethod): PaymentMethod { /* ... */ }
    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod { /* ... */ }
    public function confirmPaymentIntent(string $intentId, array $params = []): PaymentIntent { /* ... */ }
    public function capturePaymentIntent(string $intentId, array $params = []): PaymentIntent { /* ... */ }
    public function cancelPaymentIntent(string $intentId, array $params = []): PaymentIntent { /* ... */ }
    public function createRefund(string $paymentIntentId, array $params = []): array { /* ... */ }
    public function retrievePaymentIntent(string $intentId): PaymentIntent { /* ... */ }
}
```


### How to use it

After implementing your gateway (for example, `AcmePayGateway` above), you can use it exactly like the built-in gateways.
Instantiate it with a PSR-18 HTTP client and PSR-17 factories, then call the methods defined by `PaymentGatewayInterface`:

```php
<?php

declare(strict_types=1);

use App\Payment\Gateways\AcmePayGateway;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;

// $httpClient: PSR-18 client
// $requestFactory: PSR-17 request factory
// $streamFactory: PSR-17 stream factory

$gateway = new AcmePayGateway($httpClient, $requestFactory, $streamFactory);

// 1) (Optional) Create a customer in the provider
$customer = $gateway->createCustomer(new Customer(
    email: 'buyer@example.com',
    name: 'Buyer',
));

// 2) Create a payment intent (amount is in minor units, e.g. cents)
$intent = $gateway->createPaymentIntent(new PaymentIntent(
    amount: 1999,
    currency: 'USD',
    customerId: $customer->id,
    metadata: ['order_id' => 'ORDER-1001'],
));

// 3) If the provider requires buyer approval via redirect, send the buyer to:
$approvalUrl = $intent->nextAction['redirect_to_url']['url'] ?? null;

// 4) Later (after approval), confirm / capture (if your gateway uses these steps)
$intent = $gateway->confirmPaymentIntent($intent->id);
$intent = $gateway->capturePaymentIntent($intent->id);

// 5) Refund (full or partial, depending on your gateway constraints)
$refund = $gateway->createRefund($intent->id, ['amount' => 1999]);
```

#### Minimal required methods

`PaymentGatewayInterface` requires implementing **all** methods below:

- Customer: `createCustomer()`, `retrieveCustomer()`, `updateCustomer()`, `deleteCustomer()`
- Payment methods: `createPaymentMethod()`, `attachPaymentMethod()`
- Payment intents: `createPaymentIntent()`, `retrievePaymentIntent()`, `confirmPaymentIntent()`, `capturePaymentIntent()`,
  `cancelPaymentIntent()`
- Refunds: `createRefund()`

For a gateway that only supports *payments + refunds* (and does not have a customer / payment-method concept), the minimum
you typically implement with real provider calls is:

- `createPaymentIntent()`, `retrievePaymentIntent()`, `cancelPaymentIntent()`, `createRefund()`
- plus `confirmPaymentIntent()` / `capturePaymentIntent()` **if** your provider has a multi-step confirmation/capture flow

Customer and payment-method operations can be implemented as no-ops (returning the input model) or by throwing
`PaymentException` if the provider does not support them. Document that behavior in README.


Best practices:

1. Throw `PaymentException` (or subclasses) on any gateway-side errors.
2. Use idempotency keys where the provider supports them.
3. Add unit tests with a fake/spy HTTP client, and integration tests with real credentials (optional).
4. Document any gateway-specific behavior (approval redirects, delayed capture, refund constraints).

## Documentation

- [Internals](docs/internals.md)

If you need help or have a question, the [Yii Forum](https://forum.yiiframework.com/c/yii-3-0/63) is a good place
for that. You may also check out other [Yii Community Resources](https://www.yiiframework.com/community).

## Testing

Unit tests:

```bash
vendor/bin/phpunit
```

Integration tests (PayPal / Robokassa real API exchange):

1) Install dev dependencies
2) Copy config templates and fill credentials:

```bash
cp tests/config/paypal.php.dist tests/config/paypal.php
cp tests/config/robokassa.php.dist tests/config/robokassa.php
```

3) Run integration tests (they will be skipped if config is missing):

```bash
vendor/bin/phpunit --group integration
```

## License

The Yii payments is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)

## Webhooks

Release 1 provides the payment webhook processing subsystem for public use.
It defines a provider-independent entry point for incoming payment webhooks while keeping the application in control of HTTP routing and provider-specific endpoint configuration.

The application owns the HTTP endpoint, selects the configured provider for that endpoint, and builds a `WebhookInput` from the original request data.
The library validates the provider request, processes the payment webhook through the provider-specific pipeline, and returns a normalized `WebhookContext` that application code can use together with preserved raw request data.

### Release 1 — Payment Webhooks

#### Webhook Flow

```mermaid
%%{init: {"theme":"base","themeVariables": {
  "background":"transparent",
  "fontFamily":"ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif",
  "primaryColor":"#0f172a",
  "primaryTextColor":"#e2e8f0",
  "primaryBorderColor":"#94a3b8",
  "lineColor":"#94a3b8",
  "secondaryColor":"#052e16",
  "tertiaryColor":"#1e293b",
  "clusterBkg":"#0b1220",
  "clusterBorder":"#334155",
  "activationBkgColor":"#1e293b",
  "activationBorderColor":"#94a3b8",
  "noteBkgColor":"#111827",
  "noteTextColor":"#e5e7eb",
  "noteBorderColor":"#94a3b8"
}}}%%
sequenceDiagram
    participant A as Application Endpoint
    participant I as WebhookInput
    participant C as WebhookProcessorInterface
    participant V as Provider Validator
    participant P as Provider Processor
    participant F as Recognizer / Parser / Mapper
    participant R as WebhookContext

    A->>A: Receive HTTP request for one configured provider
    A->>I: Build from raw body, headers, query params, form body params
    A->>C: process(input)
    C->>C: Preserve raw request data
    C->>V: validate(input)
    alt Validation fails
        V-->>C: WebhookValidationResult::failure(reason)
        C->>R: Build ValidationFailed context with raw input and raw data
        R-->>A: WebhookContext
    else Validation succeeds
        V-->>C: WebhookValidationResult::success()
        C->>P: process(input)
        P->>F: recognize payment event
        P->>F: parse provider payload
        P->>F: map to common payment event and status
        F-->>P: WebhookProcessingResult
        P-->>C: WebhookProcessingResult
        C->>R: Build normalized context with raw input and raw data
        R-->>A: WebhookContext
    end
    A->>A: Use normalized payment data and preserved raw request data
```

The main idea is:

- the application owns the HTTP endpoint and configures each endpoint for one payment provider;
- the application converts the incoming HTTP request into a library-specific `WebhookInput`;
- the common webhook processor provides one entry point for all supported payment providers;
- provider-specific validation verifies signatures, secrets, headers, and other authenticity markers before event processing starts;
- provider-specific processing recognizes payment-related events and selects the correct normalization path;
- provider payload parsing converts the original request payload into an internal representation instead of requiring application code to read raw provider payloads directly;
- mapping converts parsed provider data into a common `WebhookContext` that application code can handle consistently;
- common payment status extraction gives the application one payment-status model across providers;
- raw request data remains available for logging, diagnostics, fallback handling, and provider-specific application logic;
- unknown or unsupported events return predictable context instead of breaking the integration;
- each gateway declares its supported, partially supported, and unsupported webhook capabilities explicitly;
- Release 1 includes minimal documentation and support matrix information needed to use payment webhook support.

Architecture boundaries:

- incoming webhook processing is separate from the outbound `PaymentGatewayInterface` used to create, capture, cancel, and refund payments;
- the application owns HTTP routing, controller/action code, endpoint-to-provider mapping, secrets, and provider-specific webhook configuration;
- the library works with the `WebhookInput` passed by application code and does not create framework controllers or HTTP responses;
- provider auto-detection from a raw HTTP request is not part of Release 1. The application selects the provider for the configured endpoint and passes its identifier in `WebhookInput`;
- provider-specific webhook processors are configured in the webhook object graph, not retrieved from outbound gateway instances such as `StripeGateway->getWebhookHandler()`.

### Common Contracts

#### `WebhookProcessorInterface`

The common public entry point for incoming webhook processing. Application code passes a
`WebhookInput` built from the original HTTP request and receives a normalized
`WebhookContext` for the handled payment webhook.

```php
interface WebhookProcessorInterface
{
    public function process(WebhookInput $input): WebhookContext;
}
```

The common processor flow is provider-independent:

1. preserve the raw request data from `WebhookInput`;
2. resolve and run the provider-specific validator, when one is configured;
3. return a `ValidationFailed` context immediately when validation fails;
4. resolve the provider-specific processor by `WebhookInput::$providerId`;
5. return a predictable `ValidationFailed` context when no provider processor is registered;
6. delegate successful provider processing to the resolved provider processor and wrap the
   resulting processing outcome into `WebhookContext`.

The common entry point does not expose provider gateway methods and does not depend on an
outbound `PaymentGatewayInterface` instance.

#### `WebhookProviderProcessorInterface`

Provider-specific webhook event processor registered under a stable provider identifier.
The identifier returned by `getProviderId()` is the value used by the common processor to
match the processor with `WebhookInput::$providerId`. The provider processor is called only
after the matching provider validator returns a successful `WebhookValidationResult`.

```php
interface WebhookProviderProcessorInterface
{
    public function getProviderId(): string;

    public function process(WebhookInput $input): WebhookProcessingResult;
}
```

`process()` runs the provider-specific payment webhook pipeline and returns
`WebhookProcessingResult`. The common processor then wraps that processing outcome into the
final `WebhookContext` returned to application code.

#### `WebhookProviderProcessorRegistry`

Registry and resolver for provider-specific webhook processors. The common processor uses it
to select the processor that matches `WebhookInput::$providerId` after validation succeeds.
When no processor is registered for the selected provider, the registry provides a predictable
missing-provider processing result instead of letting the integration fail with an ambiguous
null dereference or framework error.

```php
final class WebhookProviderProcessorRegistry
{
    public function __construct(WebhookProviderProcessorInterface ...$processors);

    public function get(string $providerId): ?WebhookProviderProcessorInterface;

    public function missingProcessorResult(string $providerId, ?WebhookRawData $rawData = null): WebhookProcessingResult;

    public function has(string $providerId): bool;
}
```

#### `WebhookProviderValidatorInterface`

Provider-specific verification of signatures, secrets, headers, and other authenticity markers for one payment provider.
Validation is a separate stage that runs before provider event recognition, payload parsing, and mapping.
A validator is selected by the same provider identifier that the application passes in `WebhookInput::$providerId`.

```php
interface WebhookProviderValidatorInterface
{
    public function getProviderId(): string;

    public function validate(WebhookInput $input): WebhookValidationResult;
}
```

A failed validation result is fail-fast: the common processor returns a `ValidationFailed`
`WebhookContext` and does not call the provider processor.

#### `WebhookProviderValidatorRegistry`

Registry and resolver for provider-specific webhook validators. The common processor uses it to select
the validator that matches `WebhookInput::$providerId` before running provider event processing.
If no validator is registered for a provider, the common processor can continue only when the selected
provider processing flow does not require a validator; provider-specific authenticity failures must be
reported by `WebhookValidationResult::failure()`.

```php
final class WebhookProviderValidatorRegistry
{
    public function __construct(WebhookProviderValidatorInterface ...$validators);

    public function get(string $providerId): ?WebhookProviderValidatorInterface;

    public function has(string $providerId): bool;
}
```

#### Provider processing stages

After provider-specific validation succeeds, the provider processor runs the R1 payment webhook
pipeline. These stages are provider-specific implementation details behind
`WebhookProviderProcessorInterface`; application code should pass `WebhookInput` to the common
processor and handle the returned `WebhookContext`, not build these intermediate objects itself.

The target provider processing flow is:

1. recognize whether the provider event is a payment-related webhook event;
2. parse the provider payload from the original request data;
3. represent parsed provider data as an intermediate `WebhookPayload`;
4. map the intermediate payload to the common processing result used to build `WebhookContext`;
5. extract the common payment status when the provider payload contains payment state.

#### `WebhookEventRecognizerInterface`

Provider-specific recognition of payment-related webhook events. The recognizer reads the
original provider request represented by `WebhookInput`, extracts the raw provider event type,
and maps it to the normalized `WebhookEventType` used by the R1 payment webhook contract.

Recognition is an internal provider-processing stage. It does not replace `WebhookInput`, does
not return `WebhookContext`, and must not require application code to parse provider payloads.
Unknown provider event names and known-but-unsupported event types are converted later into
`WebhookProcessingResult` statuses.

```php
interface WebhookEventRecognizerInterface
{
    public function recognizeProviderEventType(WebhookInput $input): ?string;

    public function recognizeEventType(string $providerEventType): ?WebhookEventType;
}
```

#### `WebhookPayloadParserInterface`

Provider-specific parsing of original request data into an intermediate provider payload used by
the provider processing pipeline. The parser reads from `WebhookInput` after validation and
recognition; it does not replace `WebhookInput` as the application-owned boundary object and does
not produce the final application-facing result.

```php
interface WebhookPayloadParserInterface
{
    public function parsePayload(
        WebhookInput $input,
        WebhookEventType $eventType,
        ?string $providerEventType = null,
    ): WebhookPayload;
}
```

#### `PaymentWebhookMapperInterface`

Provider-specific mapping of the intermediate `WebhookPayload` into the common payment-oriented
processing outcome. The mapper converts provider-specific payment data, payment event type, and
payment state into `WebhookProcessingResult`; the common processor then wraps that result into the
final `WebhookContext` returned to application code.

```php
interface PaymentWebhookMapperInterface
{
    public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult;

    public function extractPaymentStatus(WebhookPayload $payload): ?string;
}
```

#### `WebhookCapabilitiesInterface`

Explicit declaration of webhook support and supported entity kinds for a provider-specific processor.

```php
interface WebhookCapabilitiesInterface
{
    public function supportsWebhooks(): bool;
    public function supportsWebhookEntity(string $entityKind): bool;
}
```

#### `WebhookInput`

The application-owned input object passed into the library.

`WebhookInput` must contain the original request data that provider-specific validators and processors need.
The application builds it at the HTTP boundary and passes provider fields as received from the provider.
Do not rename, normalize, or map provider fields to application-specific names before passing them to this object.

- `rawBody` is the exact HTTP request body string. For JSON webhooks, keep the complete JSON payload here.
- `headers` contains the original HTTP request headers received by the endpoint.
- `queryParams` contains original provider fields from the HTTP query string, with provider field names preserved.
- `bodyParams` contains original provider fields from a form-like request body, such as
  `application/x-www-form-urlencoded` or `multipart/form-data`, with provider field names preserved.
  For JSON webhooks, pass an empty array and keep the payload in `rawBody`.

```php
readonly class WebhookInput
{
    public function __construct(
        public string $rawBody,
        public array $headers = [],
        public array $queryParams = [],
        public array $bodyParams = [],
        public ?string $providerId = null,
    ) {
    }
}
```

#### `WebhookValidationResult`

Validation result for the incoming webhook request. The result uses a single-reason model:

- `success()` creates a successful result without a reason;
- `failure(WebhookReason $reason)` creates a failed result with exactly one reason;
- a successful result must not contain a reason;
- a failed result must contain a `WebhookReason`.

```php
readonly class WebhookValidationResult
{
    public function __construct(
        public bool $isValid,
        public ?WebhookReason $reason = null,
    ) {
    }

    public static function success(): self;

    public static function failure(WebhookReason $reason): self;
}
```

`WebhookValidationResult` is only for the provider-specific request validation stage. Later
recognition, parsing, mapping, unknown-event, and unsupported-event outcomes are represented by
webhook processing results and final `WebhookContext` reasons, not by validation errors.

#### `WebhookReasonCode`

Machine-readable reason code for a webhook validation or processing outcome.
The value must be a non-empty string and is intended for application branching, logging, and tests.
Reason codes keep failure handling predictable without exposing provider-specific exception details.

```php
readonly class WebhookReasonCode
{
    public function __construct(
        public string $value,
    ) {
    }

    public function __toString(): string;
}
```

#### `WebhookReason`

Human-readable explanation for a webhook validation or processing outcome.
Validation and processing failures use a single reason object instead of an array of errors.
The reason combines a machine-readable `WebhookReasonCode`, a non-empty message, and an optional provider event type when it is known.

```php
readonly class WebhookReason
{
    public function __construct(
        public WebhookReasonCode $code,
        public string $message,
        public ?string $providerEventType = null,
    ) {
    }
}
```

#### `WebhookProcessingStatus`

Normalized status of the provider webhook processing outcome. These statuses describe the result
of the common processor plus the provider-specific payment webhook pipeline:

- `Processed` means the request was validated, recognized, parsed, and mapped successfully;
- `ValidationFailed` means processing stopped before provider event processing started. This
  includes provider-specific validation failures and missing provider processors;
- `UnknownEvent` means the request was valid, but the provider event type is not recognized by
  the payment webhook mapping;
- `UnsupportedEvent` means the request was valid and recognized, but the event is outside the
  supported R1 payment webhook normalization scope.

```php
enum WebhookProcessingStatus: string
{
    case Processed = 'processed';
    case ValidationFailed = 'validation_failed';
    case UnknownEvent = 'unknown_event';
    case UnsupportedEvent = 'unsupported_event';
}
```

#### `WebhookProcessingResult`

Provider webhook processing outcome returned by `WebhookProviderProcessorInterface` and then
wrapped by the common processor into `WebhookContext`. It represents both successful payment
webhook processing and predictable non-success outcomes such as validation failure, unknown
events, unsupported events, or a missing provider processor.

`WebhookProcessingResult` is not the final application-facing context. Application code receives
`WebhookContext`; provider processors return `WebhookProcessingResult` so the common processor
can attach the original `WebhookInput` and preserved `WebhookRawData` consistently.

```php
readonly class WebhookProcessingResult
{
    public function __construct(
        public WebhookProcessingStatus $status,
        public ?WebhookEventType $eventType = null,
        public ?WebhookReason $reason = null,
        public ?WebhookRawData $rawData = null,
    ) {
    }

    public static function validationFailed(?WebhookRawData $rawData = null, ?WebhookReason $reason = null): self;

    public static function missingProviderProcessor(string $providerId, ?WebhookRawData $rawData = null): self;

    public static function unknownEvent(string $providerEventType): self;

    public static function unsupportedEvent(
        WebhookEventType $eventType,
        ?string $providerEventType = null,
        ?WebhookRawData $rawData = null,
    ): self;
}
```

Validation failures are limited to failures before provider event processing starts. Later
recognition, parsing, and mapping outcomes must not be added to `WebhookValidationResult`; they
are represented by `WebhookProcessingResult` statuses and the corresponding reason on
`WebhookContext`.

#### `WebhookPayload`

Intermediate internal representation of the parsed provider webhook payload. `WebhookPayload` is
created by the provider parser after validation and event recognition, then consumed by the
payment mapper. It carries provider-specific parsed data together with normalized event metadata
needed to build `WebhookProcessingResult`.

`WebhookPayload` is not the application-owned input object and it is not the final result returned
to application code. Applications pass `WebhookInput` into the common processor and receive
`WebhookContext` back.

```php
readonly class WebhookPayload
{
    public function __construct(
        public ?string $providerId = null,
        public ?WebhookEventType $eventType = null,
        public ?string $providerEventType = null,
        public array $data = [],
        public ?string $paymentStatus = null,
        public ?WebhookRawData $rawData = null,
    ) {
    }
}
```

#### `WebhookContext`

`WebhookContext` is the final normalized context returned to application code after validation
and provider-specific processing. It is the application-facing webhook outcome, while
`WebhookPayload` remains an intermediate provider-processing representation.

The context exposes the provider identifier, recognized common event type, processing status,
single failure reason for the matching failure category when processing does not complete, and
the original input/raw data for diagnostics and application-level handling.

It does not expose the older draft-model fields such as `isValid`, `isSupported`, `provider`,
`entityKind`, `paymentIntent`, `rawBody`, or `rawHeaders` as direct context fields. Raw request
data is available through `rawInput` and `rawData`.

```php
readonly class WebhookContext
{
    public function __construct(
        public ?string $providerId = null,
        public ?WebhookEventType $eventType = null,
        public ?WebhookProcessingStatus $status = null,
        public ?WebhookReason $validationFailureReason = null,
        public ?WebhookReason $unsupportedEventReason = null,
        public ?WebhookReason $unknownEventReason = null,
        public ?WebhookInput $rawInput = null,
        public ?WebhookRawData $rawData = null,
    ) {
    }
}
```

### Example: Application Flow

For each webhook endpoint, the application selects the provider explicitly and wires the common
`WebhookProcessorInterface` with the provider-specific processor and validator used by that endpoint.
It then converts the incoming HTTP request into `WebhookInput`, passes it to the processor,
and works with the resulting `WebhookContext`.

```php
<?php

declare(strict_types=1);

use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorRegistry;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookStripeValidator;

final class ApplicationStripePaymentWebhookProcessor implements WebhookProviderProcessorInterface
{
    public function getProviderId(): string
    {
        return 'stripe';
    }

    public function process(WebhookInput $input): WebhookProcessingResult
    {
        $payload = json_decode($input->rawBody, true);
        $providerEventType = is_array($payload) && isset($payload['type']) && is_string($payload['type'])
            ? $payload['type']
            : 'unknown';

        $rawData = new WebhookRawData(
            rawBody: $input->rawBody,
            headers: $input->headers,
            payload: $payload,
            providerEventType: $providerEventType,
            queryParams: $input->queryParams,
            bodyParams: $input->bodyParams,
        );

        return match ($providerEventType) {
            'payment_intent.succeeded' => new WebhookProcessingResult(
                status: WebhookProcessingStatus::Processed,
                eventType: WebhookEventType::PaymentSucceeded,
                rawData: $rawData,
            ),
            default => WebhookProcessingResult::unknownEvent($providerEventType),
        };
    }
}

function createStripeWebhookProcessor(string $signingSecret): WebhookProcessorInterface
{
    return new WebhookProcessor(
        providerProcessorRegistry: new WebhookProviderProcessorRegistry(
            new ApplicationStripePaymentWebhookProcessor(),
        ),
        providerValidatorRegistry: new WebhookProviderValidatorRegistry(
            new WebhookStripeValidator($signingSecret),
        ),
    );
}

/** @var string $rawBody */
/** @var array<string, string|list<string>> $rawHeaders */
/** @var array<string, mixed> $queryParams Original provider fields from the HTTP query string. */
/** @var array<string, mixed> $bodyParams Original provider fields from a form-like request body; use [] for JSON webhooks. */

$processor = createStripeWebhookProcessor('YOUR_STRIPE_WEBHOOK_SECRET');
$input = new WebhookInput(
    rawBody: $rawBody,
    headers: $rawHeaders,
    queryParams: $queryParams,
    bodyParams: $bodyParams,
    providerId: 'stripe',
);

$context = $processor->process($input);

if ($context->status === WebhookProcessingStatus::Processed) {
    $eventType = $context->eventType;
    $rawData = $context->rawData;

    // Application-specific handling:
    // - update the local payment record;
    // - use the normalized payment event type;
    // - store raw webhook data for diagnostics when needed.
} else {
    handleWebhookProcessingFailure($context);
}

function handleWebhookProcessingFailure(WebhookContext $context): void
{
    // Application-specific fallback handling:
    // - log validation failures;
    // - ignore unknown or unsupported events;
    // - keep raw webhook data for diagnostics when available.
}
```

If the context status is `WebhookProcessingStatus::ValidationFailed`, the request failed before provider event processing.
This includes provider-specific request validation failures and missing provider processors, for example with the
`missing_provider_processor` reason code.
If the context status is `WebhookProcessingStatus::UnknownEvent` or `WebhookProcessingStatus::UnsupportedEvent`,
the request is valid but the event is unknown, unsupported, or outside the current normalization scope.
In these cases, the context still exposes the raw request data needed for diagnostics or custom fallback handling.

### Out of Scope for Release 1

- refund-specific webhook normalization;
- subscription / recurring webhook normalization;
- framework controllers, routing, and endpoint wiring inside this library;
- provider auto-detection or account resolution from the incoming HTTP request;
- provider-specific extras API;
- rich error hierarchy beyond practical minimum;
- heavy webhook testing toolkit.

### Planned Webhook Releases

#### Release 1 — Payment Webhooks

Common payment webhook processing for all supported gateways:
unified webhook handling entry point, provider-specific request validation,
payment event recognition, payload parsing,
mapping to `WebhookContext`, common payment status extraction,
raw request access, unknown or unsupported event handling,
and explicit capability declaration per gateway.

#### Release 2 — Refund Webhooks

Extension of the same subsystem for refund-related events:
refund event recognition, refund payload mapping,
common refund status extraction, safe public API extension,
and documentation updates.

#### Release 3 — Subscription / Recurring Webhooks

Support for recurring-payment and subscription lifecycle events:
capability declaration per gateway, recurring event recognition,
mapping into the common webhook result, common recurring status extraction,
explicit unsupported behavior, and documentation updates.

#### Release 4 — Webhook API Polish

API hardening and usability improvements:
richer event taxonomy, advanced error model,
provider-specific extras, event ID / idempotency helpers,
better testing tooling, and fuller documentation.
