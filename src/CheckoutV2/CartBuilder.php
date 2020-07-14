<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\TestFramework\Helper\Bootstrap;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;

/**
 * Builder to be used by fixtures
 */
class CartBuilder
{
    const CUSTOM_OPTIONS_KEY = 'custom_options';

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
     * @var ProductOptionBuilder
     */
    private $productOptionBuilder;

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
    private $cartItems = [];

    public function __construct(
        CartManagementInterface $cartManagement,
        ProductRepositoryInterface $productRepository,
        CartItemInterfaceFactory $cartItemFactory,
        ProductOptionBuilder $productOptionBuilder,
        CartItemRepositoryInterface $cartItemRepository
    ) {
        $this->cartManagement = $cartManagement;
        $this->productRepository = $productRepository;
        $this->cartItemFactory = $cartItemFactory;
        $this->productOptionBuilder = $productOptionBuilder;
        $this->cartItemRepository = $cartItemRepository;
    }

    public static function aCart(ObjectManagerInterface $objectManager = null): CartBuilder
    {
        if ($objectManager === null) {
            $objectManager = Bootstrap::getObjectManager();
        }

        return new static(
            $objectManager->create(CartManagementInterface::class),
            $objectManager->create(ProductRepositoryInterface::class),
            $objectManager->create(CartItemInterfaceFactory::class),
            $objectManager->create(ProductOptionBuilder::class),
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

        $customer = $builder->customerBuilder->build();
        $this->cartManagement->createEmptyCartForCustomer($customer->getId());

        $cart = $builder->cartManagement->getCartForCustomer($customer->getId());

        //fixme(nr): import addresses during checkout, not in cart
        foreach ($customer->getAddresses() as $address) {
            if ($address->isDefaultBilling()) {
                $cart->getBillingAddress()->importCustomerAddressData($address);
            }

            if ($address->isDefaultShipping()) {
                $cart->getShippingAddress()->importCustomerAddressData($address);
            }
        }

        foreach ($builder->cartItems as $cartItemData) {
            $cartItem = $builder->cartItemFactory->create();
            $options = isset($cartItemData['product_options']) ? $cartItemData['product_options'] : [];
            $customOptions = isset($options[self::CUSTOM_OPTIONS_KEY]) ? $cartItemData[self::CUSTOM_OPTIONS_KEY] : [];
            foreach ($customOptions as $optionId => $optionValue) {
                $builder->productOptionBuilder->addCustomOption((string) $optionId, (string) $optionValue);
            }

            $cartItem->setSku($cartItemData['sku']);
            $cartItem->setQty($cartItemData['qty']);
            $cartItem->setQuoteId($cart->getId());
            $cartItem->setProductOption($builder->productOptionBuilder->build());

            $builder->cartItemRepository->save($cartItem);
        }

        return $cart;
    }
}
