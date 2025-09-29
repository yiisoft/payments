<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * Customer represents the payer object for Orders v2.
 * At payer.name, only given_name and surname are supported.
 */
final class Customer
{
    /**
     * Payer's name (given_name and surname).
     *
     * @var PayerName|null
     */
    public ?PayerName $name = null;

    /**
     * Payer's email address.
     *
     * @var string|null
     */
    public ?string $emailAddress = null;

    /**
     * Payer's phone details.
     *
     * @var Phone|null
     */
    public ?Phone $phone = null;

    /**
     * Payer's billing address.
     *
     * @var Address|null
     */
    public ?Address $address = null;

    /**
     * @param PayerName|null $name          Payer name (given/surname).
     * @param string|null    $emailAddress  Email of payer.
     * @param Phone|null     $phone         Phone details.
     * @param Address|null   $address       Payer address.
     */
    public function __construct(
        ?PayerName $name = null,
        ?string $emailAddress = null,
        ?Phone $phone = null,
        ?Address $address = null
    ) {
        $this->name = $name;
        $this->emailAddress = $emailAddress;
        $this->phone = $phone;
        $this->address = $address;
    }

    /**
     * Serialize to Orders v2 payer structure.
     *
     * @return array{name?:array,email_address?:string,phone?:array,address?:array}
     */
    public function toArray(): array
    {
        $out = [];
        if ($this->name !== null) {
            $out['name'] = $this->name->toArray();
        }
        if ($this->emailAddress !== null) {
            $out['email_address'] = $this->emailAddress;
        }
        if ($this->phone !== null) {
            $out['phone'] = $this->phone->toArray();
        }
        if ($this->address !== null) {
            $out['address'] = $this->address->toArray();
        }
        return $out;
    }
}
