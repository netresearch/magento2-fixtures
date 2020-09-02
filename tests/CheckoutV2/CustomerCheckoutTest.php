<?php

namespace TddWizard\Fixtures\CheckoutV2;

use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\CustomerFixturePool;
use TddWizard\Fixtures\Sales\OrderFixturePool;

/**
 * @magentoDbIsolation enabled
 */
class CustomerCheckoutTest extends TestCase
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
     * @var OrderFixturePool
     */
    private $orderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productFixtures = new ProductFixturePool();
        $this->customerFixtures = new CustomerFixturePool();
        $this->cartFixtures = new CartFixturePool();
        $this->orderFixtures = new OrderFixturePool();
    }

    protected function tearDown(): void
    {
        $this->productFixtures->rollback();
        $this->customerFixtures->rollback();
        $this->cartFixtures->rollback();
        $this->orderFixtures->rollback();

        parent::tearDown();
    }

    /**
     * Test checkout with reserved order id.
     *
     * - Assert that reserved order id ends up as order increment id.
     * - Assert that all cart items are added to the order.
     * - Assert that qty ordered matches qty in cart.
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     *
     * @throws \Exception
     */
    public function checkoutWithReservedOrderId()
    {
        $reservedOrderId = '123456789';
        $cartItems = [
            'foo' => ['qty' => 2],
            'bar' => ['qty' => 3],
        ];

        // create customer
        $customer = CustomerBuilder::aCustomer()
            ->withAddresses(AddressBuilder::anAddress()->asDefaultShipping()->asDefaultBilling())
            ->build();
        $this->customerFixtures->add($customer);

        // create products
        foreach ($cartItems as $sku => $cartItemData) {
            $product = ProductBuilder::aSimpleProduct()->withSku($sku)->build();
            $this->productFixtures->add($product);
        }

        // create cart
        $cartBuilder = CartBuilder::forCustomer($customer->getId())->withReservedOrderId($reservedOrderId);
        foreach ($cartItems as $sku => $cartItemData) {
            $cartBuilder = $cartBuilder->withItem($sku, $cartItemData['qty']);
        }
        $cart = $cartBuilder->build();
        $this->cartFixtures->add($cart);

        // check out and place order
        $checkout = CustomerCheckout::withCart($cart);
        $order = $checkout->placeOrder();
        $this->orderFixtures->add($order);

        self::assertSame($reservedOrderId, $order->getIncrementId());

        $orderItems = $order->getItems();
        self::assertNotEmpty($orderItems);
        self::assertCount(count($cartItems), $orderItems);
        foreach ($orderItems as $orderItem) {
            self::assertEquals($cartItems[$orderItem->getSku()]['qty'], $orderItem->getQtyOrdered());
        }
    }

    /**
     * Test checkout with explicitly given address data.
     *
     * - Assert that order address equals customer address
     * - Assert that billing and shipping address are the same.
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     *
     * @throws \Exception
     */
    public function checkoutWithAddress()
    {
        $cartItemSku = 'test-123';

        // create customer
        $customer = CustomerBuilder::aCustomer()
            ->withAddresses(
                AddressBuilder::anAddress()
                    ->withFirstname($firstName = 'Wasch')
                    ->withLastname($lastName = 'Bär')
                    ->withStreet($street = 'Trierer Str. 791')
                    ->withTelephone($phone = '555-666-777')
                    ->withCompany($company = 'integer_net')
                    ->withCountryId($country = 'DE')
                    ->withRegionId($region = 88)
                    ->withPostcode($postalCode = '52078')
                    ->withCity($city = 'Aachen')
                    ->asDefaultBilling()
                    ->asDefaultShipping()
            )
            ->build();

        $this->customerFixtures->add($customer);

        // create product
        $product = ProductBuilder::aSimpleProduct()->withSku($cartItemSku)->build();
        $this->productFixtures->add($product);

        // create cart
        $cartBuilder = CartBuilder::forCustomer($customer->getId())->withItem($cartItemSku);
        $cart = $cartBuilder->build();
        $this->cartFixtures->add($cart);

        // check out and place order
        $checkout = CustomerCheckout::withCart($cart);
        $order = $checkout->placeOrder();
        $this->orderFixtures->add($order);

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        self::assertSame($firstName, $billingAddress->getFirstname());
        self::assertSame($lastName, $billingAddress->getLastname());
        self::assertSame($street, implode(PHP_EOL, $billingAddress->getStreet()));
        self::assertSame($phone, $billingAddress->getTelephone());
        self::assertSame($company, $billingAddress->getCompany());
        self::assertSame($country, $billingAddress->getCountryId());
        self::assertSame($region, (int) $billingAddress->getRegionId());
        self::assertSame($postalCode, $billingAddress->getPostcode());
        self::assertSame($city, $billingAddress->getCity());

        self::assertSame($firstName, $shippingAddress->getFirstname());
        self::assertSame($lastName, $shippingAddress->getLastname());
        self::assertSame($street, implode(PHP_EOL, $shippingAddress->getStreet()));
        self::assertSame($phone, $shippingAddress->getTelephone());
        self::assertSame($company, $shippingAddress->getCompany());
        self::assertSame($country, $shippingAddress->getCountryId());
        self::assertSame($region, (int) $shippingAddress->getRegionId());
        self::assertSame($postalCode, $shippingAddress->getPostcode());
        self::assertSame($city, $shippingAddress->getCity());
    }

    /**
     * Test checkout with different billing and shipping address data.
     *
     * - Assert that order billing address equals customer default billing address
     * - Assert that order shipping address equals customer default shipping address
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     *
     * @throws \Exception
     */
    public function checkoutWithAddresses()
    {
        $cartItemSku = 'test-234';

        // create customer
        $customer = CustomerBuilder::aCustomer()
            ->withAddresses(
                AddressBuilder::anAddress()
                    ->withFirstname($billingFirstName = 'Wasch')
                    ->withLastname($billingLastName = 'Bär')
                    ->withStreet($billingStreet = 'Trierer Str. 791')
                    ->withTelephone($billingPhone = '555-666-777')
                    ->withCompany($billingCompany = 'integer_net')
                    ->withCountryId($billingCountry = 'DE')
                    ->withRegionId($billingRegion = 88)
                    ->withPostcode($billingPostalCode = '52078')
                    ->withCity($billingCity = 'Aachen')
                    ->asDefaultBilling(),
                AddressBuilder::anAddress()
                    ->withFirstname($shippingFirstName = 'Foo')
                    ->withLastname($shippingLastName = 'Bar')
                    ->withStreet($shippingStreet = 'Bahnhofstr. 911')
                    ->withTelephone($shippingPhone = '111-222-222')
                    ->withCompany($shippingCompany = 'NR')
                    ->withCountryId($shippingCountry = 'DE')
                    ->withRegionId($shippingRegion = 91)
                    ->withPostcode($shippingPostalCode = '04103')
                    ->withCity($shippingCity = 'Leipzig')
                    ->asDefaultShipping()
            )
            ->build();

        $this->customerFixtures->add($customer);

        // create product
        $product = ProductBuilder::aSimpleProduct()->withSku($cartItemSku)->build();
        $this->productFixtures->add($product);

        // create cart
        $cartBuilder = CartBuilder::forCustomer($customer->getId())->withItem($cartItemSku);
        $cart = $cartBuilder->build();
        $this->cartFixtures->add($cart);

        // check out and place order
        $checkout = CustomerCheckout::withCart($cart);
        $order = $checkout->placeOrder();
        $this->orderFixtures->add($order);

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        self::assertSame($billingFirstName, $billingAddress->getFirstname());
        self::assertSame($billingLastName, $billingAddress->getLastname());
        self::assertSame($billingStreet, implode(PHP_EOL, $billingAddress->getStreet()));
        self::assertSame($billingPhone, $billingAddress->getTelephone());
        self::assertSame($billingCompany, $billingAddress->getCompany());
        self::assertSame($billingCountry, $billingAddress->getCountryId());
        self::assertSame($billingRegion, (int) $billingAddress->getRegionId());
        self::assertSame($billingPostalCode, $billingAddress->getPostcode());
        self::assertSame($billingCity, $billingAddress->getCity());

        self::assertSame($shippingFirstName, $shippingAddress->getFirstname());
        self::assertSame($shippingLastName, $shippingAddress->getLastname());
        self::assertSame($shippingStreet, implode(PHP_EOL, $shippingAddress->getStreet()));
        self::assertSame($shippingPhone, $shippingAddress->getTelephone());
        self::assertSame($shippingCompany, $shippingAddress->getCompany());
        self::assertSame($shippingCountry, $shippingAddress->getCountryId());
        self::assertSame($shippingRegion, (int) $shippingAddress->getRegionId());
        self::assertSame($shippingPostalCode, $shippingAddress->getPostcode());
        self::assertSame($shippingCity, $shippingAddress->getCity());
    }

    /**
     * Test checking out multiple times in a row. Errors may occur if application state is not set back properly.
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     *
     * @throws \Exception
     */
    public function subsequentCheckouts()
    {
        $fooSku = 'test-345';
        $barSku = 'test-456';

        // create customers
        $fooCustomer = CustomerBuilder::aCustomer()->withAddresses(
            AddressBuilder::anAddress()->withLastname($fooBillingLastName = 'Foo')->asDefaultBilling(),
            AddressBuilder::anAddress()->withLastname($fooShippingLastName = 'Fox')->asDefaultShipping()
        )->build();
        $barCustomer = CustomerBuilder::aCustomer()->withAddresses(
            AddressBuilder::anAddress()->withLastname($barBillingLastName = 'Bar')->asDefaultBilling(),
            AddressBuilder::anAddress()->withLastname($barShippingLastName = 'Baz')->asDefaultShipping()
        )->build();

        $this->customerFixtures->add($fooCustomer);
        $this->customerFixtures->add($barCustomer);

        // create products
        $fooProduct = ProductBuilder::aSimpleProduct()->withSku($fooSku)->build();
        $barProduct = ProductBuilder::aSimpleProduct()->withSku($barSku)->build();

        $this->productFixtures->add($fooProduct);
        $this->productFixtures->add($barProduct);

        // create foo cart
        $fooCart = CartBuilder::forCustomer($fooCustomer->getId())
            ->withReservedOrderId($fooOrderIncrementId = '1001')
            ->withItem($fooSku)
            ->build();
        $this->cartFixtures->add($fooCart);

        // check out and place foo order
        $checkout = CustomerCheckout::withCart($fooCart);
        $fooOrder = $checkout->placeOrder();
        $this->orderFixtures->add($fooOrder);

        // create bar cart
        $barCart = CartBuilder::forCustomer($barCustomer->getId())
            ->withReservedOrderId($barOrderIncrementId = '2002')
            ->withItem($barSku)
            ->build();
        $this->cartFixtures->add($barCart);

        // check out and place bar order
        $checkout = CustomerCheckout::withCart($barCart);
        $barOrder = $checkout->placeOrder();
        $this->orderFixtures->add($barOrder);

        self::assertSame($fooOrderIncrementId, $fooOrder->getIncrementId());
        self::assertSame($fooBillingLastName, $fooOrder->getBillingAddress()->getLastname());
        self::assertSame($fooShippingLastName, $fooOrder->getShippingAddress()->getLastname());

        self::assertSame($barOrderIncrementId, $barOrder->getIncrementId());
        self::assertSame($barBillingLastName, $barOrder->getBillingAddress()->getLastname());
        self::assertSame($barShippingLastName, $barOrder->getShippingAddress()->getLastname());
    }
}
