<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * PurchaseUnit defines one contractual unit within an order.
 */
final class PurchaseUnit
{
    /**
     * Amount for this purchase unit.
     *
     * @var Amount
     */
    public Amount $amount;

    /**
     * Shipping details for this unit.
     *
     * @var Shipping|null
     */
    public ?Shipping $shipping;

    /**
     * Optional reference identifier for reconciliation.
     *
     * @var string|null
     */
    public ?string $referenceId;

    /**
     * Optional description for the unit.
     *
     * @var string|null
     */
    public ?string $description;

    /**
     * Optional custom identifier for business reconciliation.
     *
     * @var string|null
     */
    public ?string $customId;

    /**
     * @param Amount       $amount       Monetary amount.
     * @param Shipping|null $shipping    Shipping details.
     * @param string|null   $referenceId Reference identifier.
     * @param string|null   $description Description.
     * @param string|null   $customId    Custom identifier.
     */
    public function __construct(
        Amount $amount,
        ?Shipping $shipping = null,
        ?string $referenceId = null,
        ?string $description = null,
        ?string $customId = null
    ) {
        $this->amount = $amount;
        $this->shipping = $shipping;
        $this->referenceId = $referenceId;
        $this->description = $description;
        $this->customId = $customId;
    }

    /**
     * Serialize to purchase_units[n] structure.
     *
     * @return array{
     *   amount:array,
     *   shipping?:array,
     *   reference_id?:string,
     *   description?:string,
     *   custom_id?:string
     * }
     */
    public function toArray(): array
    {
        $out = ['amount' => $this->amount->toArray()];
        if ($this->shipping    !== null) $out['shipping']     = $this->shipping->toArray();
        if ($this->referenceId !== null) $out['reference_id'] = $this->referenceId;
        if ($this->description !== null) $out['description']  = $this->description;
        if ($this->customId    !== null) $out['custom_id']    = $this->customId;
        return $out;
    }
}
