<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * Address represents an international address aligned with Orders v2 fields.
 */
final class Address
{
    /**
     * Street address line 1.
     *
     * @var string
     */
    public string $addressLine1;

    /**
     * Street address line 2 (optional).
     *
     * @var string|null
     */
    public ?string $addressLine2;

    /**
     * City or locality (admin_area_2).
     *
     * @var string
     */
    public string $adminArea2;

    /**
     * State or province code (admin_area_1) (optional).
     *
     * @var string|null
     */
    public ?string $adminArea1;

    /**
     * Postal or ZIP code.
     *
     * @var string
     */
    public string $postalCode;

    /**
     * Two-letter ISO country code.
     *
     * @var string
     */
    public string $countryCode;

    /**
     * @param string      $addressLine1 Line 1.
     * @param string      $adminArea2   City/locality.
     * @param string      $postalCode   Postal/ZIP.
     * @param string      $countryCode  Country code (ISO 3166-1 alpha-2).
     * @param string|null $addressLine2 Line 2 (optional).
     * @param string|null $adminArea1   State/province (optional).
     */
    public function __construct(
        string $addressLine1,
        string $adminArea2,
        string $postalCode,
        string $countryCode,
        ?string $addressLine2 = null,
        ?string $adminArea1 = null
    ) {
        $this->addressLine1 = $addressLine1;
        $this->addressLine2 = $addressLine2;
        $this->adminArea2   = $adminArea2;
        $this->adminArea1   = $adminArea1;
        $this->postalCode   = $postalCode;
        $this->countryCode  = $countryCode;
    }

    /**
     * Serialize to portable address structure for payer/shipping.
     *
     * @return array{
     *   address_line_1:string,
     *   admin_area_2:string,
     *   postal_code:string,
     *   country_code:string,
     *   address_line_2?:string,
     *   admin_area_1?:string
     * }
     */
    public function toArray(): array
    {
        $out = [
            'address_line_1' => $this->addressLine1,
            'admin_area_2'   => $this->adminArea2,
            'postal_code'    => $this->postalCode,
            'country_code'   => $this->countryCode,
        ];

        if ($this->addressLine2 !== null) {
            $out['address_line_2'] = $this->addressLine2;
        }
        if ($this->adminArea1 !== null) {
            $out['admin_area_1'] = $this->adminArea1;
        }

        return $out;
    }
}
