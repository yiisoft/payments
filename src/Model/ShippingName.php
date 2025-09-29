<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * ShippingName represents the recipient name under purchase_units[n].shipping.name.
 * The shipping.name path supports full_name.
 */
final class ShippingName
{
    /**
     * The full name of the shipping recipient.
     *
     * @var string
     */
    public string $fullName;

    /**
     * @param string $fullName Full name for shipping recipient.
     */
    public function __construct(string $fullName)
    {
        $this->fullName = $fullName;
    }

    /**
     * Serialize to shipping.name structure.
     *
     * @return array{full_name:string}
     */
    public function toArray(): array
    {
        return ['full_name' => $this->fullName];
    }
}
