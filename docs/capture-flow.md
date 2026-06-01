# Capture flow

A card payment can be taken in one step or two.

- **One step (capture now).** The money moves as soon as the customer pays. Use this when you ship or deliver right away.
- **Two steps (authorize now, capture later).** The gateway places a hold on the customer's funds without moving them, and you capture that hold later when you fulfill the order. The hold reserves the amount and expires on its own if you never capture it.

The two-step flow is what most processors call *authorize* and *capture*. The package exposes both through `PaymentGatewayInterface`, though how far each provider supports the hold differs (see the table below).

## The four stages

| Stage | Interface method | What happens |
| --- | --- | --- |
| CREATE | `createPaymentIntent()` | Create an intent for an amount and currency and hand the customer a way to pay (a client secret or a redirect URL in `nextAction`). |
| CONFIRM | `confirmPaymentIntent()` | The customer submits their card data, usually on the provider's hosted page or client SDK. What this call does is provider-specific: Stripe confirms server-side, PayPal and Robokassa re-fetch the current state after off-site approval, YooKassa proceeds to capture. |
| AUTHORIZE (HOLD) | no dedicated method | The gateway checks the card and reserves the amount without moving it. There is no separate authorize call; it happens during confirm or during the first capture call, depending on the provider. |
| CAPTURE | `capturePaymentIntent()` | Charge the held amount. This is the final transaction. |

To release a hold instead of capturing it, call `cancelPaymentIntent()`.

`PaymentIntent::$status` is returned as the provider reports it; the package does not translate provider status vocabularies into one shared set. Compare against the value your provider uses rather than assuming the same constant works everywhere.

## Choosing the flow: `captureMethod`

`PaymentIntent::$captureMethod` selects the flow at creation time:

- `false` or `null` - capture immediately. The charge is final once the customer pays (Stripe `capture_method=automatic`, PayPal `CAPTURE` order).
- `true` - authorize only. The gateway holds the funds for a later `capturePaymentIntent()` call (Stripe `capture_method=manual`, PayPal `AUTHORIZE` order).

Not every provider honors the flag - see the table below.

```php
use Yiisoft\Payments\Models\PaymentIntent;

$intent = $gateway->createPaymentIntent(new PaymentIntent(
    amount: 20000,
    currency: 'usd',
    customerId: $customer->id,
    paymentMethodId: $paymentMethod->id,
    captureMethod: true,
));

// The customer confirms the card (hosted page or client SDK). Once the gateway
// authorizes it, the funds are held and the intent waits for capture. The exact
// status string depends on the provider.

$captured = $gateway->capturePaymentIntent($intent->id);
```

If the order falls through before you capture, release the hold:

```php
$gateway->cancelPaymentIntent($intent->id);
```

## When to hold

Hold the money when you charge at fulfillment, not at checkout.

- **Hotel booking.** Authorize the room price when the guest books. The funds are reserved but not taken. Capture when the guest arrives, or release the hold if they cancel in time.
- **Food delivery.** Authorize the order total when the customer places the order. Capture once the courier delivers. If the kitchen can't fulfill it, release the hold and the customer is never charged.

In both cases the customer commits at order time, but you only move money when you deliver.

## Provider support

The hold depends on what each provider's API offers, so the same `captureMethod: true` does not behave identically everywhere.

| Provider | Hold then capture | Notes |
| --- | --- | --- |
| Stripe | Yes | `captureMethod: true` sends `capture_method=manual`. After the customer confirms, the payment is authorized (Stripe reports `requires_capture`) and `capturePaymentIntent()` charges it. |
| PayPal | Yes, two calls | `captureMethod: true` creates an `AUTHORIZE` order. Call `capturePaymentIntent($orderId)` to authorize it; it returns the `authorization_id` in metadata. Call `capturePaymentIntent($orderId, ['authorization_id' => ...])` again - the same order id, with the authorization id in params - to capture. This two-call round trip through one method is a current implementation detail rather than a clean one-call capture. |
| YooKassa | Yes, always | The payment is always created with `capture=false`, so it holds first and `captureMethod` is ignored. `capturePaymentIntent()` charges it (`confirmPaymentIntent()` delegates to the same capture call). One-step immediate capture is not exposed by this gateway. |
| Robokassa | No | Invoice-based: the customer pays on a hosted page and the charge is final. `confirmPaymentIntent()` and `capturePaymentIntent()` re-fetch the invoice state, they do not place or capture a hold. |

## Partial capture

Some providers let you capture less than the authorized amount (a smaller final bill than the hold, for example). When supported, pass the amount in `capturePaymentIntent()` parameters; the accepted keys are provider-specific. Capturing less than the hold releases the remainder.
