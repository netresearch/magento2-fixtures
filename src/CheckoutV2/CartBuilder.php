<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Catalog\Api\Data\CustomOptionInterfaceFactory;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Quote\Api\Data\ProductOptionExtensionInterfaceFactory;
use Magento\Quote\Api\Data\ProductOptionInterface;
use Magento\Quote\Api\Data\ProductOptionInterfaceFactory;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Builder to be used by fixtures
 */
class CartBuilder
{
    const CUSTOM_OPTIONS_KEY = 'custom_options';

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var CartItemRepositoryInterface
     */
    private $cartItemRepository;

    /**
     * @var CartItemInterfaceFactory
     */
    private $cartItemFactory;

    /**
     * @var ProductOptionInterfaceFactory
     */
    private $productOptionFactory;

    /**
     * @var ProductOptionExtensionInterfaceFactory
     */
    private $productOptionExtensionFactory;

    /**
     * @var CustomOptionInterfaceFactory
     */
    private $customOptionFactory;

    /**
     * @var int
     */
    private $customerId;

    /**
     * @var mixed[][]
     */
    private $cartItems = [];

    /**
     * @var string
     */
    private $reservedOrderId;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        CartManagementInterface $cartManagement,
        CartItemRepositoryInterface $cartItemRepository,
        CartItemInterfaceFactory $cartItemFactory,
        ProductOptionInterfaceFactory $productOptionFactory,
        ProductOptionExtensionInterfaceFactory $productOptionExtensionFactory,
        CustomOptionInterfaceFactory $customOptionFactory,
        int $customerId
    ) {
        $this->cartRepository = $cartRepository;
        $this->cartManagement = $cartManagement;
        $this->cartItemRepository = $cartItemRepository;
        $this->cartItemFactory = $cartItemFactory;
        $this->productOptionFactory = $productOptionFactory;
        $this->productOptionExtensionFactory = $productOptionExtensionFactory;
        $this->customOptionFactory = $customOptionFactory;
        $this->customerId = $customerId;
    }

    public static function forCustomer(int $customerId): CartBuilder
    {
        $objectManager = Bootstrap::getObjectManager();

        return new static(
            $objectManager->create(CartRepositoryInterface::class),
            $objectManager->create(CartManagementInterface::class),
            $objectManager->create(CartItemRepositoryInterface::class),
            $objectManager->create(CartItemInterfaceFactory::class),
            $objectManager->create(ProductOptionInterfaceFactory::class),
            $objectManager->create(ProductOptionExtensionInterfaceFactory::class),
            $objectManager->create(CustomOptionInterfaceFactory::class),
            $customerId
        );
    }

    private function buildProductOption(array $options): ProductOptionInterface
    {
        $productOption = $this->productOptionFactory->create();
        if (empty($options)) {
            return $productOption;
        }

        if (empty($options[self::CUSTOM_OPTIONS_KEY]) || !is_array($options[self::CUSTOM_OPTIONS_KEY])) {
            // currently only custom options are supported. no bundle, configurable, downloadable, etc.
            return $productOption;
        }

        $productOptionExtension = $this->productOptionExtensionFactory->create();

        $customOptions = [];
        foreach ($options[self::CUSTOM_OPTIONS_KEY] as $optionId => $optionValue) {
            $customOption = $this->customOptionFactory->create();
            $customOption->setOptionId((string) $optionId);
            $customOption->setOptionValue((string) $optionValue);
            $customOptions[] = $customOption;
        }

        $productOptionExtension->setCustomOptions($customOptions);
        $productOption->setExtensionAttributes($productOptionExtension);

        return $productOption;
    }

    public function withReservedOrderId(string $orderIncrementId): CartBuilder
    {
        $builder = clone $this;
        $builder->reservedOrderId = $orderIncrementId;

        return $builder;
    }

    public function withItem(string $sku, int $qty = 1, array $productOptions = []): CartBuilder
    {
        $builder = clone $this;

        $builder->cartItems[] = [
            'sku' => $sku,
            'qty' => $qty,
            'product_options' => $productOptions, // custom_options, downloadable_options, configurable_item_options
        ];

        return $builder;
    }

    /**
     * @return CartInterface
     * @throws \Exception
     */
    public function build(): CartInterface
    {
        $this->cartManagement->createEmptyCartForCustomer($this->customerId);
        $cart = $this->cartManagement->getCartForCustomer($this->customerId);

        if (empty($this->cartItems)) {
            throw new \Exception('No items set, cannot create empty cart.');
        }

        foreach ($this->cartItems as $cartItemData) {
            $cartItem = $this->cartItemFactory->create();
            $productOption = $this->buildProductOption($cartItemData['product_options']);

            $cartItem->setQuoteId($cart->getId());
            $cartItem->setSku($cartItemData['sku']);
            $cartItem->setQty($cartItemData['qty']);
            $cartItem->setProductOption($productOption);

            $this->cartItemRepository->save($cartItem);
        }

        if ($this->reservedOrderId) {
            $cart->setReservedOrderId($this->reservedOrderId);

            // force items reload before save, otherwise they have no item ID
            $cart->setItems([]);
            $this->cartRepository->save($cart);
        }

        return $cart;
    }
}
