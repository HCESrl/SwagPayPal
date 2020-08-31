<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\Util\Lifecycle;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Swag\PayPal\Pos\Exception\ExistingPosSalesChannelsException;
use Swag\PayPal\Pos\Setting\Service\InformationDefaultService;
use Swag\PayPal\SwagPayPal;
use Swag\PayPal\Util\PaymentMethodUtil;

class ActivateDeactivate
{
    /**
     * @var EntityRepositoryInterface
     */
    private $paymentRepository;

    /**
     * @var PaymentMethodUtil
     */
    private $paymentMethodUtil;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelTypeRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $shippingRepository;

    public function __construct(
        PaymentMethodUtil $paymentMethodUtil,
        EntityRepositoryInterface $paymentRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $salesChannelTypeRepository,
        EntityRepositoryInterface $shippingRepository
    ) {
        $this->paymentMethodUtil = $paymentMethodUtil;
        $this->paymentRepository = $paymentRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->salesChannelTypeRepository = $salesChannelTypeRepository;
        $this->shippingRepository = $shippingRepository;
    }

    public function activate(Context $context): void
    {
        $this->setPaymentMethodsIsActive(true, $context);
        $this->addPosSalesChannelType($context);
    }

    public function deactivate(Context $context): void
    {
        $this->setPaymentMethodsIsActive(false, $context);
        $this->checkPosSalesChannels($context);
        $this->removePosSalesChannelType($context);
        $this->removePosDefaultEntities($context);
    }

    private function setPaymentMethodsIsActive(bool $active, Context $context): void
    {
        $payPalPaymentMethodId = $this->paymentMethodUtil->getPayPalPaymentMethodId($context);

        if ($payPalPaymentMethodId === null) {
            return;
        }

        $updateData = [[
            'id' => $payPalPaymentMethodId,
            'active' => $active,
        ]];

        $this->paymentRepository->update($updateData, $context);
    }

    private function addPosSalesChannelType(Context $context): void
    {
        $this->salesChannelTypeRepository->upsert([
            [
                'id' => SwagPayPal::SALES_CHANNEL_TYPE_POS,
                'iconName' => 'default-money-cash',
                'translations' => [
                    'en-GB' => [
                        'name' => 'iZettle',
                        'manufacturer' => 'Shopware',
                        'description' => 'Sales Channel for synchronisation with your iZettle account',
                    ],
                    'de-DE' => [
                        'name' => 'iZettle',
                        'manufacturer' => 'Shopware',
                        'description' => 'Sales Channel für die Synchronisation mit Deinem iZettle-Account',
                    ],
                ],
            ],
        ], $context);
    }

    private function removePosSalesChannelType(Context $context): void
    {
        $this->salesChannelTypeRepository->delete([['id' => SwagPayPal::SALES_CHANNEL_TYPE_POS]], $context);
    }

    /**
     * @throws ExistingPosSalesChannelsException
     */
    private function checkPosSalesChannels(Context $context): void
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(
                new EqualsFilter('typeId', SwagPayPal::SALES_CHANNEL_TYPE_POS)
            );

        /** @var EntitySearchResult $result */
        $result = $context->disableCache(function (Context $context) use ($criteria): EntitySearchResult {
            return $this->salesChannelRepository->search($criteria, $context);
        });

        if ($result->getTotal() > 0) {
            $names = $result->getEntities()->map(function (SalesChannelEntity $item): string {
                return (string) $item->getName();
            });

            throw new ExistingPosSalesChannelsException($result->getTotal(), $names);
        }
    }

    private function removePosDefaultEntities(Context $context): void
    {
        $this->paymentRepository->delete([InformationDefaultService::POS_PAYMENT_METHOD_ID], $context);
        $this->shippingRepository->delete([InformationDefaultService::POS_SHIPPING_METHOD_ID], $context);
    }
}
