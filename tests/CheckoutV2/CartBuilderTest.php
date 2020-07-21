<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ShippingAddressManagementInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Catalog\ProductFixtureRollback;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\CustomerFixture;
use TddWizard\Fixtures\Customer\CustomerFixtureRollback;

/**
 * @magentoDbIsolation enabled
 */
class CartBuilderTest extends TestCase
{
    /**
     * @var CartFixture
     */
    private $cartFixture;

    /**
     * @var ProductFixture[]
     */
    private $productFixtures;

    /**
     * @var CustomerFixture
     */
    private $customerFixture;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ShippingAddressManagementInterface
     */
    private $addressManagement;

    protected function setUp()
    {
        $this->cartRepository = Bootstrap::getObjectManager()->create(CartRepositoryInterface::class);
        $this->addressManagement = Bootstrap::getObjectManager()->create(ShippingAddressManagementInterface::class);
    }

    protected function tearDown()
    {
        CartFixtureRollback::create()->execute($this->cartFixture);
        ProductFixtureRollback::create()->execute(...$this->productFixtures);
        CustomerFixtureRollback::create()->execute($this->customerFixture);

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

        $this->customerFixture = new CustomerFixture(CustomerBuilder::aCustomer()->build());
        $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($sku)->build());
        $this->cartFixture = new CartFixture(
            CartBuilder::forCustomer($this->customerFixture->getId())
                ->withItem($sku)
                ->build()
        );

        $cart = $this->cartRepository->get($this->cartFixture->getId());
        $cartItems = $cart->getItems();
        self::assertNotEmpty($cartItems);
        self::assertCount(1, $cartItems);
        foreach ($cartItems as $cartItem) {
            self::assertSame(1, $cartItem->getQty());
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
            'foo' => ['qty' => 2],
            'bar' => ['qty' => 3],
        ];

        $this->customerFixture = new CustomerFixture(CustomerBuilder::aCustomer()->build());
        $cartBuilder = CartBuilder::forCustomer($this->customerFixture->getId());

        foreach ($cartItemData as $sku => $data) {
            $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($sku)->build());
            $cartBuilder = $cartBuilder->withItem($sku, $data['qty']);
        }

        $this->cartFixture = new CartFixture($cartBuilder->build());

        $cart = $this->cartRepository->get($this->cartFixture->getId());
        $cartItems = $cart->getItems();
        self::assertCount(count($cartItemData), $cartItems);
        foreach ($cartItems as $cartItem) {
            self::assertSame($cartItemData[$cartItem->getSku()]['qty'], $cartItem->getQty());
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
            'foo' => ['qty' => 2, 'options' => [42 => 'foobar', 303 => 'foxbaz']],
            'bar' => ['qty' => 3, 'options' => []],
        ];

        $this->customerFixture = new CustomerFixture(CustomerBuilder::aCustomer()->build());
        $cartBuilder = CartBuilder::forCustomer($this->customerFixture->getId());

        foreach ($cartItemData as $sku => $data) {
            $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($sku)->build());
            $cartBuilder = $cartBuilder->withItem(
                $sku,
                $data['qty'],
                [CartBuilder::CUSTOM_OPTIONS_KEY => $data['options']]
            );
        }

        $this->cartFixture = new CartFixture($cartBuilder->build());

        $cart = $this->cartRepository->get($this->cartFixture->getId());
        $cartItems = $cart->getItems();
        foreach ($cartItems as $cartItem) {
            $buyRequest = $cartItem->getOptionByCode('info_buyRequest')->getValue();
            foreach ($cartItemData[$cartItem->getSku()]['options'] as $optionId => $optionValue) {
                self::assertContains((string) $optionId, $buyRequest);
                self::assertContains((string) $optionValue, $buyRequest);
            }
        }
    }
}
