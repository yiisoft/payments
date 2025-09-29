<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * PhoneNumber represents a national-format phone number for payer.phone.phone_number.
 */
final class PhoneNumber
{
    /**
     * National-format phone number (no country code).
     *
     * @var string
     */
    public string $nationalNumber;

    /**
     * @param string $nationalNumber National phone number digits.
     */
    public function __construct(string $nationalNumber)
    {
        $this->nationalNumber = $nationalNumber;
    }

    /**
     * Serialize to phone_number structure.
     *
     * @return array{national_number:string}
     */
    public function toArray(): array
    {
        return ['national_number' => $this->nationalNumber];
    }
}
