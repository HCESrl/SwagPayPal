<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\IZettle\Sync\Inventory;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Swag\PayPal\IZettle\Api\Exception\IZettleApiException;
use Swag\PayPal\IZettle\Api\Inventory\BulkChanges;
use Swag\PayPal\IZettle\Api\Service\Inventory\RemoteCalculator;
use Swag\PayPal\IZettle\Resource\InventoryResource;
use Swag\PayPal\IZettle\Sync\Context\InventoryContext;

class RemoteUpdater
{
    /**
     * @var InventoryResource
     */
    private $inventoryResource;

    /**
     * @var RemoteCalculator
     */
    private $remoteCalculator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        InventoryResource $inventoryResource,
        RemoteCalculator $remoteCalculator,
        LoggerInterface $logger
    ) {
        $this->inventoryResource = $inventoryResource;
        $this->remoteCalculator = $remoteCalculator;
        $this->logger = $logger;
    }

    public function updateRemote(ProductCollection $productCollection, InventoryContext $inventoryContext): ProductCollection
    {
        $remoteChanges = new BulkChanges();
        $changedProducts = new ProductCollection();
        foreach ($productCollection->getElements() as $productEntity) {
            if ($productEntity->getChildCount() > 0) {
                continue;
            }

            $productChange = $this->remoteCalculator->calculateRemoteChange($productEntity, $inventoryContext);
            if ($productChange === null) {
                continue;
            }

            $remoteChanges->addProductChange($productChange);

            $changeAmount = $this->remoteCalculator->getChangeAmount($productEntity, $inventoryContext);
            $productEntity->addExtension(StockChange::STOCK_CHANGE_EXTENSION, new StockChange($changeAmount));

            $changedProducts->add($productEntity);
        }

        if (\count($remoteChanges->getProductChanges()) === 0) {
            return $changedProducts;
        }

        $remoteChanges->setReturnBalanceForLocationUuid($inventoryContext->getStoreUuid());

        try {
            $status = $this->inventoryResource->changeInventoryBulk($inventoryContext->getIZettleSalesChannel(), $remoteChanges);
        } catch (IZettleApiException $iZettleApiException) {
            $this->logger->error('Inventory sync error: ' . $iZettleApiException);

            return $changedProducts;
        }

        foreach ($changedProducts as $changedProduct) {
            /** @var StockChange|null $stockChange */
            $stockChange = $changedProduct->getExtension(StockChange::STOCK_CHANGE_EXTENSION);

            if ($stockChange === null) {
                continue;
            }

            $this->logger->info('Changed remote inventory of {productName} by {change}', [
                'product' => $changedProduct,
                'productName' => $changedProduct->getName() ?? 'variant',
                'change' => $stockChange->getStockChange(),
            ]);
        }

        if ($status === null || \count($status->getVariants()) === 0) {
            return $changedProducts;
        }

        foreach ($status->getVariants() as $variant) {
            $inventoryContext->addRemoteInventory($variant);
        }

        return $changedProducts;
    }
}
