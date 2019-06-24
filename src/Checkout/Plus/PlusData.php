<?php declare(strict_types=1);

namespace Swag\PayPal\Checkout\Plus;

use Shopware\Core\Framework\Struct\Struct;

class PlusData extends Struct
{
    /**
     * @var string
     */
    protected $approvalUrl;

    /**
     * @var string
     */
    protected $customerCountryIso;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var string
     */
    protected $customerSelectedLanguage;

    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var string
     */
    protected $remotePaymentId;

    public function getApprovalUrl(): string
    {
        return $this->approvalUrl;
    }

    public function getCustomerCountryIso(): string
    {
        return $this->customerCountryIso;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getCustomerSelectedLanguage(): string
    {
        return $this->customerSelectedLanguage;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function getRemotePaymentId(): string
    {
        return $this->remotePaymentId;
    }
}
