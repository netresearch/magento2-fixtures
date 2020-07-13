<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Quote\Model\Quote\ProductOptionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;

/**
 * Builder to be used by fixtures
 */
class CartBuilder
{
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CartItemInterfaceFactory
     */
    private $cartItemFactory;

    /**
     * @var ProductOptionFactory
     */
    private $productOptionFactory;

    /**
     * @var CartItemRepositoryInterface
     */
    private $cartItemRepository;

    /**
     * @var CustomerBuilder
     */
    private $customerBuilder;

    /**
     * Cart items data array. Compare REST API payload.
     *
     * @var mixed[][]
     */
    private $cartItems;

    public function __construct(
        CartManagementInterface $cartManagement,
        ProductRepositoryInterface $productRepository,
        CartItemInterfaceFactory $cartItemFactory,
        ProductOptionFactory $productOptionFactory,
        CartItemRepositoryInterface $cartItemRepository
    ) {
        $this->cartManagement = $cartManagement;
        $this->productRepository = $productRepository;
        $this->cartItemFactory = $cartItemFactory;
        $this->productOptionFactory = $productOptionFactory;
        $this->cartItemRepository = $cartItemRepository;
    }

    public static function aCart(ObjectManagerInterface $objectManager = null): CartBuilder
    {
        if ($objectManager === null) {
            $objectManager = Bootstrap::getObjectManager();
        }

        return new self(
            $objectManager->create(CartManagementInterface::class),
            $objectManager->create(ProductRepositoryInterface::class),
            $objectManager->create(CartItemInterfaceFactory::class),
            $objectManager->create(ProductOptionFactory::class),
            $objectManager->create(CartItemRepositoryInterface::class)
        );
    }

    public function withCustomer(CustomerBuilder $customerBuilder): CartBuilder
    {
        $builder = clone $this;
        $builder->customerBuilder = $customerBuilder;

        return $builder;
    }

    public function withItem($sku, $qty = 1, $productOptions = []): CartBuilder
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
        $builder = clone $this;

        if (empty($builder->customerBuilder)) {
            // init customer
            $builder->customerBuilder = CustomerBuilder::aCustomer()
                ->withAddresses(AddressBuilder::anAddress()->asDefaultBilling()->asDefaultShipping());
        }

        if (empty($this->cartItems)) {
            $this->cartItems = [
                ['sku' => 'test', 'qty' => 1],
            ];
        }

        $customer = $this->customerBuilder->build();
        $this->cartManagement->createEmptyCartForCustomer($customer->getId());

        $cart = $this->cartManagement->getCartForCustomer($customer->getId());

        foreach ($this->cartItems as $cartItemData) {
            $cartItem = $this->cartItemFactory->create([
                CartItemInterface::KEY_SKU => $cartItemData['sku'],
                CartItemInterface::KEY_QTY => $cartItemData['qty'],
                CartItemInterface::KEY_QUOTE_ID => $cart->getId(),
                //todo(nr): ProductOptionInterface support (custom, downloadable, configurable)
            ]);

            $this->cartItemRepository->save($cartItem);
        }

        return $cart;
    }
}
