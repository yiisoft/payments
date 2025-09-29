<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * Phone wraps PhoneNumber for payer.phone.
 */
final class Phone
{
    /**
     * The phone number details.
     *
     * @var PhoneNumber
     */
    public PhoneNumber $phoneNumber;

    /**
     * @param PhoneNumber $phoneNumber National phone number wrapper.
     */
    public function __construct(PhoneNumber $phoneNumber)
    {
        $this->phoneNumber = $phoneNumber;
    }

    /**
     * Serialize to payer.phone structure.
     *
     * @return array{phone_number:array{national_number:string}}
     */
    public function toArray(): array
    {
        return ['phone_number' => $this->phoneNumber->toArray()];
    }
}
