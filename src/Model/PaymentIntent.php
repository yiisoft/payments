<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * PaymentIntent represents an Orders v2 request/response container.
 */
final class PaymentIntent
{
    /**
     * Capture funds immediately after approval.
     */
    public const INTENT_CAPTURE = 'CAPTURE';

    /**
     * Authorize funds to capture later.
     */
    public const INTENT_AUTHORIZE = 'AUTHORIZE';

    /**
     * The order intent (CAPTURE or AUTHORIZE).
     *
     * @var string
     */
    public string $intent;

    /**
     * Purchase units for the order.
     *
     * @var PurchaseUnit[]
     */
    public array $purchaseUnits;

    /**
     * Payer details.
     *
     * @var Customer|null
     */
    public ?Customer $customer;

    /**
     * Application context for checkout/redirect behavior.
     *
     * @var ApplicationContext|null
     */
    public ?ApplicationContext $applicationContext;

    /**
     * Server-assigned order ID (response).
     *
     * @var string|null
     */
    public ?string $orderId = null;

    /**
     * Current order status (response).
     *
     * @var string|null
     */
    public ?string $status = null;

    /**
     * Creation timestamp (response).
     *
     * @var string|null
     */
    public ?string $createTime = null;

    /**
     * Update timestamp (response).
     *
     * @var string|null
     */
    public ?string $updateTime = null;

    /**
     * @param string                  $intent             CAPTURE or AUTHORIZE.
     * @param PurchaseUnit[]          $purchaseUnits      Purchase units list.
     * @param Customer|null           $customer           Payer info.
     * @param ApplicationContext|null $applicationContext Application context.
     */
    public function __construct(
        string $intent = self::INTENT_CAPTURE,
        array $purchaseUnits = [],
        ?Customer $customer = null,
        ?ApplicationContext $applicationContext = null
    ) {
        $this->intent = $intent;
        $this->purchaseUnits = $purchaseUnits;
        $this->customer = $customer;
        $this->applicationContext = $applicationContext;
    }

    /**
     * Serialize to Orders v2 create request.
     *
     * @return array{
     *   intent:string,
     *   purchase_units:list<array>,
     *   payer?:array,
     *   application_context?:array
     * }
     */
    public function toArray(): array
    {
        $out = [
            'intent' => $this->intent,
            'purchase_units' => array_map(fn(PurchaseUnit $pu) => $pu->toArray(), $this->purchaseUnits),
        ];
        if ($this->customer !== null) {
            $out['payer'] = $this->customer->toArray();
        }
        if ($this->applicationContext !== null) {
            $out['application_context'] = $this->applicationContext->toArray();
        }
        return $out;
    }
}
