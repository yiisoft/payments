<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * ApplicationContext provides return/cancel URLs and UX hints for checkout.
 */
final class ApplicationContext
{
    /**
     * Return URL after buyer approval.
     *
     * @var string|null
     */
    public ?string $returnUrl;

    /**
     * Cancel URL if buyer cancels.
     *
     * @var string|null
     */
    public ?string $cancelUrl;

    /**
     * Brand display name on PayPal pages.
     *
     * @var string|null
     */
    public ?string $brandName;

    /**
     * Landing page type (e.g., "LOGIN").
     *
     * @var string|null
     */
    public ?string $landingPage;

    /**
     * User action (e.g., "PAY_NOW").
     *
     * @var string|null
     */
    public ?string $userAction;

    /**
     * Shipping preference (e.g., "NO_SHIPPING").
     *
     * @var string|null
     */
    public ?string $shippingPreference;

    /**
     * @param string|null $returnUrl          Return URL after approval.
     * @param string|null $cancelUrl          Cancel URL.
     * @param string|null $brandName          Branding name.
     * @param string|null $landingPage        Landing page behavior.
     * @param string|null $userAction         User action hint.
     * @param string|null $shippingPreference Shipping hint.
     */
    public function __construct(
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
        ?string $brandName = null,
        ?string $landingPage = null,
        ?string $userAction = null,
        ?string $shippingPreference = null
    ) {
        $this->returnUrl = $returnUrl;
        $this->cancelUrl = $cancelUrl;
        $this->brandName = $brandName;
        $this->landingPage = $landingPage;
        $this->userAction = $userAction;
        $this->shippingPreference = $shippingPreference;
    }

    /**
     * Serialize to application_context structure.
     *
     * @return array{
     *   return_url?:string,
     *   cancel_url?:string,
     *   brand_name?:string,
     *   landing_page?:string,
     *   user_action?:string,
     *   shipping_preference?:string
     * }
     */
    public function toArray(): array
    {
        $out = [];
        if ($this->returnUrl          !== null) $out['return_url']          = $this->returnUrl;
        if ($this->cancelUrl          !== null) $out['cancel_url']          = $this->cancelUrl;
        if ($this->brandName          !== null) $out['brand_name']          = $this->brandName;
        if ($this->landingPage        !== null) $out['landing_page']        = $this->landingPage;
        if ($this->userAction         !== null) $out['user_action']         = $this->userAction;
        if ($this->shippingPreference !== null) $out['shipping_preference'] = $this->shippingPreference;
        return $out;
    }
}
