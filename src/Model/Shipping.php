<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * Shipping captures purchase unit shipping details.
 */
final class Shipping
{
    /**
     * Name of the shipping recipient (full_name supported here).
     *
     * @var ShippingName|null
     */
    public ?ShippingName $name;

    /**
     * Shipping address.
     *
     * @var Address|null
     */
    public ?Address $address;

    /**
     * @param ShippingName|null $name    Full name at shipping.name.
     * @param Address|null      $address Shipping address.
     */
    public function __construct(?ShippingName $name = null, ?Address $address = null)
    {
        $this->name = $name;
        $this->address = $address;
    }

    /**
     * Serialize to purchase_units[n].shipping structure.
     *
     * @return array{name?:array,address?:array}
     */
    public function toArray(): array
    {
        $out = [];
        if ($this->name !== null) {
            $out['name'] = $this->name->toArray();
        }
        if ($this->address !== null) {
            $out['address'] = $this->address->toArray();
        }
        return $out;
    }
}
