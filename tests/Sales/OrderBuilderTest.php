<?php

namespace TddWizard\Fixtures\Sales;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\CheckoutV2\CartBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class OrderBuilderTest extends TestCase
{
    /**
     * @var OrderFixturePool
     */
    private $orderFixtures;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderFixtures = new OrderFixturePool();
        $this->orderRepository = Bootstrap::getObjectManager()->create(OrderRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        $this->orderFixtures->rollback();

        parent::tearDown();
    }

    /**
     * Create an order for an internally generated customer and internally generated product(s).
     *
     * Easy to set up, least flexible.
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     * @throws \Exception
     */
    public function createOrder()
    {
        $order = OrderBuilder::anOrder()->build();
        $this->orderFixtures->add($order);

        $orderFixture = $this->orderFixtures->get();

        self::assertInstanceOf(OrderInterface::class, $this->orderRepository->get($orderFixture->getId()));
        self::assertNotEmpty($orderFixture->getOrderItemQtys());
    }

    /**
     * Create an order for an internally generated customer.
     *
     * Control the product included with the order, use random item quantities.
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     * @throws \Exception
     */
    public function createOrderWithProduct()
    {
        $order = OrderBuilder::anOrder()->withProducts(ProductBuilder::aSimpleProduct())->build();
        $this->orderFixtures->add($order);

        $orderFixture = $this->orderFixtures->get();

        self::assertInstanceOf(OrderInterface::class, $this->orderRepository->get($orderFixture->getId()));
        self::assertCount(1, $orderFixture->getOrderItemQtys());
    }

    /**
     * Create an order for an internally generated customer with multiple products.
     *
     * Control the products included with the order, use random item quantities.
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     * @throws \Exception
     */
    public function createOrderWithProducts()
    {
        $order = OrderBuilder::anOrder()->withProducts(
            ProductBuilder::aSimpleProduct()->withSku('foo'),
            ProductBuilder::aSimpleProduct()->withSku('bar')
        )->build();
        $this->orderFixtures->add($order);

        $orderFixture = $this->orderFixtures->get();

        self::assertInstanceOf(OrderInterface::class, $this->orderRepository->get($orderFixture->getId()));
        self::assertCount(2, $orderFixture->getOrderItemQtys());
    }

    /**
     * Create an order for a given customer with internally generated product(s).
     *
     * Control the customer placing the order.
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     * @throws \Exception
     */
    public function createOrderWithCustomer()
    {
        $customerEmail = 'test@example.com';
        $customerBuilder = CustomerBuilder::aCustomer()
            ->withEmail($customerEmail)
            ->withAddresses(AddressBuilder::anAddress()->asDefaultBilling()->asDefaultShipping());

        $order = OrderBuilder::anOrder()->withCustomer($customerBuilder)->build();
        $this->orderFixtures->add($order);

        $orderFixture = $this->orderFixtures->get();

        self::assertInstanceOf(OrderInterface::class, $this->orderRepository->get($orderFixture->getId()));
        self::assertSame($customerEmail, $orderFixture->getCustomerEmail());
        self::assertNotEmpty($orderFixture->getOrderItemQtys());
    }

    /**
     * Create an order for a given cart.
     *
     * Complex to set up, most flexible:
     * - define products
     * - define customer
     * - set item quantities
     * - set payment and shipping method
     *
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     * @throws \Exception
     */
    public function createOrderWithCart()
    {
        $cartItems = ['foo' => 2, 'bar' => 3];
        $customerEmail = 'test@example.com';
        $paymentMethod = 'checkmo';
        $shippingMethod = 'flatrate_flatrate';

        $productBuilders = [];
        foreach ($cartItems as $sku => $qty) {
            $productBuilders[] = ProductBuilder::aSimpleProduct()->withSku($sku);
        }

        $customerBuilder = CustomerBuilder::aCustomer();
        $customerBuilder = $customerBuilder
            ->withEmail($customerEmail)
            ->withAddresses(AddressBuilder::anAddress()->asDefaultBilling()->asDefaultShipping());

        $cartBuilder = CartBuilder::forCustomer();
        foreach ($cartItems as $sku => $qty) {
            $cartBuilder = $cartBuilder->withItem($sku, $qty);
        }

        $order = OrderBuilder::anOrder()
            ->withProducts(...$productBuilders)
            ->withCustomer($customerBuilder)
            ->withCart($cartBuilder)
            ->withPaymentMethod($paymentMethod)->withShippingMethod($shippingMethod)
            ->build();
        $this->orderFixtures->add($order);

        $orderFixture = $this->orderFixtures->get();

        self::assertInstanceOf(OrderInterface::class, $this->orderRepository->get($orderFixture->getId()));
        self::assertSame($customerEmail, $orderFixture->getCustomerEmail());
        self::assertEmpty(array_diff($cartItems, $orderFixture->getOrderItemQtys()));
        self::assertSame($paymentMethod, $orderFixture->getPaymentMethod());
        self::assertSame($shippingMethod, $orderFixture->getShippingMethod());
    }

    /**
     * Create multiple orders. Assert all of them were successfully built.
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     * @throws \Exception
     */
    public function createMultipleOrders()
    {
        $shippingMethod = 'flatrate_flatrate';

        // first order, simple
        $simpleOrder = OrderBuilder::anOrder()
            ->withShippingMethod($shippingMethod)
            ->build();

        // second order, with specified cart
        $cartBuilder = CartBuilder::forCustomer();
        $orderWithCart = OrderBuilder::anOrder()
            ->withShippingMethod($shippingMethod)
            ->withProducts(ProductBuilder::aSimpleProduct()->withSku('bar'))
            ->withCart($cartBuilder->withItem('bar', 3))
            ->build();

        $this->orderFixtures->add($simpleOrder);
        $this->orderFixtures->add($orderWithCart, 'with_cart');

        // third order, with specified customer
        $orderWithCustomer = OrderBuilder::anOrder()
            ->withShippingMethod($shippingMethod)
            ->withCustomer(
                CustomerBuilder::aCustomer()
                    ->withAddresses(
                        AddressBuilder::anAddress('de_AT')
                            ->asDefaultBilling()
                            ->asDefaultShipping()
                    )
            )
            ->build();
        $this->orderFixtures->add($orderWithCustomer);

        // assert all orders were created with separate customers.
        $customerIds[$this->orderFixtures->get('simple')->getCustomerId()] = 1;
        $customerIds[$this->orderFixtures->get('with_cart')->getCustomerId()] = 1;
        $customerIds[$this->orderFixtures->get('with_customer')->getCustomerId()] = 1;
        self::assertCount(3, $customerIds);
    }

    /**
     * Create orders for faker addresses with either state or province. Assert both types have a `region_id` assigned.
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     * @throws \Exception
     */
    public function createIntlOrders()
    {
        $atLocale = 'de_AT';
        $atOrder = OrderBuilder::anOrder()
            ->withCustomer(
                CustomerBuilder::aCustomer()->withAddresses(
                    AddressBuilder::anAddress($atLocale)->asDefaultBilling()->asDefaultShipping()
                )
            )
            ->build();
        $this->orderFixtures[] = new OrderFixture($atOrder);

        $usLocale = 'en_US';
        $usOrder = OrderBuilder::anOrder()
            ->withCustomer(
                CustomerBuilder::aCustomer()->withAddresses(
                    AddressBuilder::anAddress($usLocale)->asDefaultBilling()->asDefaultShipping()
                )
            )
            ->build();
        $this->orderFixtures[] = new OrderFixture($usOrder);

        $caLocale = 'en_CA';
        $caOrder = OrderBuilder::anOrder()
            ->withCustomer(
                CustomerBuilder::aCustomer()->withAddresses(
                    AddressBuilder::anAddress($caLocale)->asDefaultBilling()->asDefaultShipping()
                )
            )
            ->build();
        $this->orderFixtures[] = new OrderFixture($caOrder);

        self::assertSame(substr($atLocale, 3, 4), $atOrder->getBillingAddress()->getCountryId());
        self::assertNotEmpty($atOrder->getBillingAddress()->getRegionId());
        self::assertSame(substr($usLocale, 3, 4), $usOrder->getBillingAddress()->getCountryId());
        self::assertNotEmpty($usOrder->getBillingAddress()->getRegionId());
        self::assertSame(substr($caLocale, 3, 4), $caOrder->getBillingAddress()->getCountryId());
        self::assertNotEmpty($caOrder->getBillingAddress()->getRegionId());
    }
}
