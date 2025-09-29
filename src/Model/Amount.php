<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * Amount models a monetary value and optional breakdown.
 */
final class Amount
{
    /**
     * ISO currency code, e.g., "USD".
     *
     * @var string
     */
    public string $currencyCode;

    /**
     * Decimal value as a string, e.g., "10.00".
     *
     * @var string
     */
    public string $value;

    /**
     * Optional amount breakdown structure (orders schema).
     *
     * @var array<string,mixed>|null
     */
    public ?array $breakdown;

    /**
     * @param string                   $currencyCode ISO currency code.
     * @param string                   $value        Decimal string value.
     * @param array<string,mixed>|null $breakdown    Optional breakdown.
     */
    public function __construct(string $currencyCode, string $value, ?array $breakdown = null)
    {
        $this->currencyCode = $currencyCode;
        $this->value = $value;
        $this->breakdown = $breakdown;
    }

    /**
     * Serialize amount to Orders v2 structure.
     *
     * @return array{currency_code:string,value:string,breakdown?:array}
     */
    public function toArray(): array
    {
        $out = [
            'currency_code' => $this->currencyCode,
            'value'         => $this->value,
        ];
        if ($this->breakdown !== null) {
            $out['breakdown'] = $this->breakdown;
        }
        return $out;
    }
}
