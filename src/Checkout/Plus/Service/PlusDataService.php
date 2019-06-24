<?php declare(strict_types=1);

namespace Swag\PayPal\Checkout\Plus\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\PayPal\Checkout\Plus\PlusData;
use Swag\PayPal\Payment\Builder\CartPaymentBuilderInterface;
use Swag\PayPal\PayPal\PartnerAttributionId;
use Swag\PayPal\PayPal\PaymentIntent;
use Swag\PayPal\PayPal\Resource\PaymentResource;
use Swag\PayPal\Setting\SwagPayPalSettingStruct;
use Swag\PayPal\Util\LocaleCodeProvider;
use Swag\PayPal\Util\PaymentMethodIdProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PlusDataService
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var CartPaymentBuilderInterface
     */
    private $paymentBuilder;

    /**
     * @var PaymentResource
     */
    private $paymentResource;

    /**
     * @var PaymentMethodIdProvider
     */
    private $paymentMethodIdProvider;

    /**
     * @var LocaleCodeProvider
     */
    private $localeCodeProvider;

    public function __construct(
        CartPaymentBuilderInterface $paymentBuilder,
        PaymentResource $paymentResource,
        RouterInterface $router,
        PaymentMethodIdProvider $paymentMethodIdProvider,
        LocaleCodeProvider $localeCodeProvider
    ) {
        $this->paymentBuilder = $paymentBuilder;
        $this->paymentResource = $paymentResource;
        $this->router = $router;
        $this->paymentMethodIdProvider = $paymentMethodIdProvider;
        $this->localeCodeProvider = $localeCodeProvider;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function getPlusData(
        Cart $cart,
        SalesChannelContext $salesChannelContext,
        SwagPayPalSettingStruct $settings
    ): ?PlusData {
        $finishUrl = $this->router->generate(
            'paypal.plus.payment.finalize.transaction',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $payment = $this->paymentBuilder->getPayment($cart, $salesChannelContext, $finishUrl, false);
        $payment->setIntent(PaymentIntent::SALE);

        $context = $salesChannelContext->getContext();
        $response = $this->paymentResource->create($payment, $context, PartnerAttributionId::PAYPAL_PLUS);
        $customer = $salesChannelContext->getCustomer();

        if ($customer === null) {
            return null;
        }

        $sandbox = $settings->getSandbox();
        $payPalData = new PlusData();
        $payPalData->assign([
            'approvalUrl' => $response->getLinks()[1]->getHref(),
            'mode' => $sandbox ? 'sandbox' : 'live',
            'customerSelectedLanguage' => $this->getPaymentWallLanguage($salesChannelContext),
            'paymentMethodId' => $this->paymentMethodIdProvider->getPayPalPaymentMethodId($context),
            'remotePaymentId' => $response->getId(),
        ]);
        $billingAddress = $customer->getDefaultBillingAddress();
        if ($billingAddress !== null) {
            $country = $billingAddress->getCountry();
            if ($country !== null) {
                $payPalData->assign(['customerCountryIso' => $country->getIso()]);
            }
        }

        return $payPalData;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getPaymentWallLanguage(SalesChannelContext $salesChannelContext): string
    {
        $languageIso = $this->localeCodeProvider->getLocaleCodeFromContext($salesChannelContext->getContext());

        $plusLanguage = 'en_GB';
        // use english as default, use german if the locale is from german speaking country (de_DE, de_AT, etc)
        // by now the PPP iFrame does not support other languages
        if (strncmp($languageIso, 'de-', 3) === 0) {
            $plusLanguage = 'de_DE';
        }

        return $plusLanguage;
    }
}
