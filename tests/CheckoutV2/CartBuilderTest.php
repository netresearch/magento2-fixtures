<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\CustomerFixturePool;

/**
 * @magentoDbIsolation enabled
 */
class CartBuilderTest extends TestCase
{
    /**
     * @var ProductFixturePool
     */
    private $productFixtures;

    /**
     * @var CustomerFixturePool
     */
    private $customerFixtures;

    /**
     * @var CartFixturePool
     */
    private $cartFixtures;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartRepository = Bootstrap::getObjectManager()->create(CartRepositoryInterface::class);

        $this->productFixtures = new ProductFixturePool();
        $this->customerFixtures = new CustomerFixturePool();
        $this->cartFixtures = new CartFixturePool();
    }

    protected function tearDown(): void
    {
        $this->productFixtures->rollback();
        $this->customerFixtures->rollback();
        $this->cartFixtures->rollback();

        parent::tearDown();
    }

    /**
     * Create a cart with given simple product
     *
     * @test
     * @throws \Exception
     */
    public function createCart()
    {
        $sku = 'test';

        $customer = CustomerBuilder::aCustomer()->build();
        $product = ProductBuilder::aSimpleProduct()->withSku($sku)->build();
        $cart = CartBuilder::forCustomer($customer->getId())->withItem($sku)->build();

        $this->customerFixtures->add($customer);
        $this->productFixtures->add($product);
        $this->cartFixtures->add($cart);

        // reload cart items
        $cart = $this->cartRepository->get($cart->getId());
        $cartItems = $cart->getItems();
        self::assertNotEmpty($cartItems);
        self::assertCount(1, $cartItems);
        foreach ($cartItems as $cartItem) {
            self::assertEquals(1, $cartItem->getQty());
            self::assertSame($sku, $cartItem->getSku());
        }
    }

    /**
     * Create a cart with given simple products and quantities
     *
     * @test
     * @throws \Exception
     */
    public function createCartWithItemQuantities()
    {
        $cartItemData = [
            'a-foo' => ['qty' => 2],
            'a-bar' => ['qty' => 3],
        ];

        $customer = CustomerBuilder::aCustomer()->build();
        $this->customerFixtures->add($customer);

        $cartBuilder = CartBuilder::forCustomer($customer->getId());

        foreach ($cartItemData as $sku => $data) {
            $product = ProductBuilder::aSimpleProduct()->withSku($sku)->build();
            $this->productFixtures->add($product);

            $cartBuilder = $cartBuilder->withItem($sku, $data['qty']);
        }

        $cart = $cartBuilder->build();
        $this->cartFixtures->add($cart);

        // reload cart items
        $cart = $this->cartRepository->get($cart->getId());
        $cartItems = $cart->getItems();
        self::assertCount(count($cartItemData), $cartItems);
        foreach ($cartItems as $cartItem) {
            self::assertEquals($cartItemData[$cartItem->getSku()]['qty'], $cartItem->getQty());
        }
    }

    /**
     * Create a cart with given product(s) and given product custom options
     *
     * @test
     * @throws \Exception
     */
    public function createCartWithCustomOptions()
    {
        $cartItemData = [
            'x-foo' => ['qty' => 2, 'options' => [42 => 'foobar', 303 => 'foxbaz']],
            'x-bar' => ['qty' => 3, 'options' => []],
        ];

        $customer = CustomerBuilder::aCustomer()->build();
        $this->customerFixtures->add($customer);

        $cartBuilder = CartBuilder::forCustomer($customer->getId());

        foreach ($cartItemData as $sku => $data) {
            $product = ProductBuilder::aSimpleProduct()->withSku($sku)->build();
            $this->productFixtures->add($product);

            $cartBuilder = $cartBuilder->withItem(
                $sku,
                $data['qty'],
                [CartBuilder::CUSTOM_OPTIONS_KEY => $data['options']]
            );
        }

        $cart = $cartBuilder->build();
        $this->cartFixtures->add($cart);

        // reload cart items
        $cart = $this->cartRepository->get($cart->getId());
        $cartItems = $cart->getItems();
        foreach ($cartItems as $cartItem) {
            $buyRequest = $cartItem->getOptionByCode('info_buyRequest')->getValue();
            foreach ($cartItemData[$cartItem->getSku()]['options'] as $optionId => $optionValue) {
                self::assertNotFalse(strpos($buyRequest, (string) $optionId));
                self::assertNotFalse(strpos($buyRequest, (string) $optionValue));
            }
        }
    }
}
