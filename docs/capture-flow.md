# Capture flow

A card payment can be taken in one step or two.

- **One step (capture now).** The money moves as soon as the customer pays. Use this when you ship or deliver right away.
- **Two steps (authorize now, capture later).** The gateway places a hold on the customer's funds without moving them, and you capture that hold later when you actually fulfill the order. The hold reserves the amount and expires on its own if you never capture it.

The two-step flow is what most processors call *authorize* and *capture*. This package exposes it through `PaymentGatewayInterface` so the calling code stays the same across providers.

## The four stages

| Stage | Interface method | What happens |
| --- | --- | --- |
| CREATE | `createPaymentIntent()` | You create an intent for an amount and currency and show the customer a way to pay. |
| CONFIRM | `confirmPaymentIntent()` | The customer submits their card data. Confirmation usually happens on the provider's hosted page or client SDK, so this call re-checks the current state for most gateways. |
| AUTHORIZE (HOLD) | done during CONFIRM | The gateway checks the card and reserves the amount. The intent lands in `requires_capture`. No money has moved yet. |
| CAPTURE | `capturePaymentIntent()` | You charge the held amount. This is the final transaction. |

To release a hold instead of capturing it, call `cancelPaymentIntent()`.

The gateway performs the authorization while it confirms the card, once you asked for a hold instead of an immediate charge. You signal that up front on `createPaymentIntent()`, so there is no separate authorize call to make by hand.

## Choosing the flow: `captureMethod`

`PaymentIntent::$captureMethod` selects the flow at creation time:

- `false` (or `null`) - capture immediately. The charge is final once the customer pays.
- `true` - authorize only. The gateway holds the funds and the intent becomes `requires_capture`, waiting for your `capturePaymentIntent()` call.

```php
use Yiisoft\Payments\Models\PaymentIntent;

$intent = $gateway->createPaymentIntent(new PaymentIntent(
    amount: 20000,
    currency: 'usd',
    customerId: $customer->id,
    paymentMethodId: $paymentMethod->id,
    captureMethod: true,
));

// $intent->status === PaymentIntent::STATUS_REQUIRES_CAPTURE

$captured = $gateway->capturePaymentIntent($intent->id);

// $captured->status === PaymentIntent::STATUS_SUCCEEDED
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

The hold step depends on what each provider's API offers, so the same `captureMethod: true` does not behave identically everywhere.

| Provider | Authorize + capture | Notes |
| --- | --- | --- |
| Stripe | Yes | `captureMethod: true` sends `capture_method=manual`; the intent reaches `requires_capture` and `capturePaymentIntent()` charges it. |
| PayPal | Yes | `captureMethod: true` creates an `AUTHORIZE` order. `capturePaymentIntent()` authorizes it (the hold); pass the resulting `authorization_id` back to `capturePaymentIntent()` to capture. |
| YooKassa | Yes (always two-step) | The payment is always created with `capture=false`, so it holds first regardless of `captureMethod`; `capturePaymentIntent()` charges it. |
| Robokassa | No | Invoice-based: the customer pays on a hosted page and the charge is final. `confirmPaymentIntent()` and `capturePaymentIntent()` re-fetch the invoice state, they do not place or capture a hold. |

## Partial capture

Some providers let you capture less than the authorized amount (for example, a smaller final bill than the hold). When supported, pass the amount in `capturePaymentIntent()` parameters; the accepted keys are provider-specific. Capturing less than the hold releases the remainder.
