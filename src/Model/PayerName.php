<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * PayerName represents the payer's name in Orders v2.
 * Only given_name and surname are supported at the payer.name path.
 */
final class PayerName
{
    /**
     * The payer's first name (given name).
     *
     * @var string
     */
    public string $givenName;

    /**
     * The payer's last name (surname).
     *
     * @var string
     */
    public string $surname;

    /**
     * @param string $givenName Payer's given name.
     * @param string $surname   Payer's surname.
     */
    public function __construct(string $givenName, string $surname)
    {
        $this->givenName = $givenName;
        $this->surname = $surname;
    }

    /**
     * Serialize to Orders v2 payer.name structure.
     *
     * @return array{given_name:string,surname:string}
     */
    public function toArray(): array
    {
        return [
            'given_name' => $this->givenName,
            'surname'    => $this->surname,
        ];
    }
}
