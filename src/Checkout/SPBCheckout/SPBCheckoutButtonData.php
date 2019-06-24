<?php declare(strict_types=1);

namespace Swag\PayPal\Checkout\SPBCheckout;

use Shopware\Core\Framework\Struct\Struct;

class SPBCheckoutButtonData extends Struct
{
    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var bool
     */
    protected $useSandbox;

    /**
     * @var string
     */
    protected $languageIso;

    /**
     * @var string
     */
    protected $currency;

    /**
     * @var string
     */
    protected $intent;

    /**
     * @var string
     */
    protected $paymentMethodId;

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getUseSandbox(): bool
    {
        return $this->useSandbox;
    }

    public function getLanguageIso(): string
    {
        return $this->languageIso;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getIntent(): string
    {
        return $this->intent;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }
}
