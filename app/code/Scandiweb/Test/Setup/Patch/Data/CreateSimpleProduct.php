<?php

declare(strict_types=1);

namespace Scandiweb\Test\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;

class CreateSimpleProduct implements DataPatchInterface
{
    /**
     * @var ProductInterfaceFactory
     */
    protected ProductInterfaceFactory $productInterfaceFactory;
    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;
    /**
     * @var State
     */
    protected State $appState;
    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;
    /**
     * @var SourceItemInterfaceFactory
     */
    protected SourceItemInterfaceFactory $sourceItemFactory;
    /**
     * @var SourceItemsSaveInterface
     */
    protected SourceItemsSaveInterface $sourceItemsSaveInterface;
    /**
     * @var EavSetup
     */
    protected EavSetup $eavSetup;
    /**
     * @var CategoryLinkManagementInterface
     */
    protected CategoryLinkManagementInterface $categoryLink;
    /**
     * @var CategoryCollectionFactory
     */
    protected CategoryCollectionFactory $categoryCollectionFactory;
    /**
     * @var array
     */
    protected array $sourceItems = [];

    /**
     * @param ProductInterfaceFactory $productInterfaceFactory
     * @param ProductRepositoryInterface $productRepository
     * @param State $appState
     * @param StoreManagerInterface $storeManager
     * @param EavSetup $eavSetup
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param SourceItemsSaveInterface $sourceItemsSaveInterface
     * @param CategoryLinkManagementInterface $categoryLink
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        ProductInterfaceFactory $productInterfaceFactory,
        ProductRepositoryInterface $productRepository,
        State $appState,
        StoreManagerInterface $storeManager,
        EavSetup $eavSetup,
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSaveInterface,
        CategoryLinkManagementInterface $categoryLink,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->appState = $appState;
        $this->productInterfaceFactory = $productInterfaceFactory;
        $this->productRepository = $productRepository;
        $this->eavSetup = $eavSetup;
        $this->storeManager = $storeManager;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->categoryLink = $categoryLink;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @return void
     */
    public function apply(): void
    {
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $categoryName = 'Default Category';
        $productName = 'Simple product';
        $productSKU = 'Simple-product';
        $productUrl = 'Simple-product';
        $productPrice = 123.45;

        $categoryId = 0;
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addAttributeToFilter('name', $categoryName)->setPageSize(1)->setCurPage(1);

        if ($categoryCollection->getSize() > 0) {
            $category = $categoryCollection->getFirstItem();
            $categoryId = $category->getId();
        }
        if (!$categoryId) {
            return;
        }

        $productCollection = $category->getProductCollection()->addAttributeToFilter('sku', ['eq' => $productSKU])->setPageSize(1)->setCurPage(1);
        if ($productCollection->getSize() > 0) {
            return;
        }

        $product = $this->productInterfaceFactory->create();

        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setWebsiteIds([$this->storeManager->getStore()->getWebsiteId()])
            ->setAttributeSetId($this->eavSetup->getDefaultAttributeSetId(Product::ENTITY))
            ->setName($productName)
            ->setUrlKey($productUrl)
            ->setSku($productSKU)
            ->setPrice($productPrice)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'is_qty_decimal' => 0, 'is_in_stock' => 1]);

        $product = $this->productRepository->save($product);

        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode('default');
        $sourceItem->setQuantity(100);
        $sourceItem->setSku($product->getSku());
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
        $this->sourceItems[] = $sourceItem;

        $this->sourceItemsSaveInterface->execute($this->sourceItems);

        $this->categoryLink->assignProductToCategories($product->getSku(), [$categoryId]);
    }

    /**
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }
}
