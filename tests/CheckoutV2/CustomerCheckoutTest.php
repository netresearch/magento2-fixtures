<?php

namespace TddWizard\Fixtures\CheckoutV2;

use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Catalog\ProductFixtureRollback;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\CustomerFixture;
use TddWizard\Fixtures\Customer\CustomerFixtureRollback;

/**
 * @magentoDbIsolation enabled
 */
class CustomerCheckoutTest extends TestCase
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

    protected function tearDown()
    {
        CartFixtureRollback::create()->execute($this->cartFixture);
        ProductFixtureRollback::create()->execute(...$this->productFixtures);
        CustomerFixtureRollback::create()->execute($this->customerFixture);

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

        $this->customerFixture = new CustomerFixture(
            CustomerBuilder::aCustomer()
                ->withAddresses(AddressBuilder::anAddress()->asDefaultShipping()->asDefaultBilling())
                ->build()
        );

        $cartBuilder = CartBuilder::forCustomer($this->customerFixture)->withReservedOrderId($reservedOrderId);

        foreach ($cartItems as $sku => $cartItemData) {
            $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($sku)->build());
            $cartBuilder = $cartBuilder->withItem($sku, $cartItemData['sku']);
        }

        $cart = $cartBuilder->build();
        $this->cartFixture = new CartFixture($cart);

        $checkout = CustomerCheckout::withCart($cart);
        $order = $checkout->placeOrder();

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
        $cartItemSku = 'test';

        $customerBuilder = CustomerBuilder::aCustomer()
            ->withAddresses(
                AddressBuilder::anAddress()
                    ->withFirstname($firstName = 'Wasch')
                    ->withLastname($lastName = 'Bär')
                    ->withStreet($street = ['Trierer Str. 791'])
                    ->withTelephone($phone = '555-666-777')
                    ->withCompany($company = 'integer_net')
                    ->withCountryId($country = 'DE')
                    ->withRegionId($region = 88)
                    ->withPostcode($postalCode = '52078')
                    ->withCity($city = 'Aachen')
                    ->asDefaultBilling()
                    ->asDefaultShipping()
            );

        $this->customerFixture = new CustomerFixture($customerBuilder->build());
        $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($cartItemSku)->build());

        $cart = CartBuilder::forCustomer($this->customerFixture)
            ->withItem($cartItemSku)
            ->build();
        $this->cartFixture = new CartFixture($cart);

        $checkout = CustomerCheckout::withCart($cart);
        $order = $checkout->placeOrder();

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        self::assertSame($firstName, $billingAddress->getFirstname());
        self::assertSame($lastName, $billingAddress->getLastname());
        self::assertSame($street, $billingAddress->getStreet());
        self::assertSame($phone, $billingAddress->getTelephone());
        self::assertSame($company, $billingAddress->getCompany());
        self::assertSame($country, $billingAddress->getCountryId());
        self::assertSame($region, (int) $billingAddress->getRegionId());
        self::assertSame($postalCode, $billingAddress->getPostcode());
        self::assertSame($city, $billingAddress->getCity());

        self::assertSame($firstName, $shippingAddress->getFirstname());
        self::assertSame($lastName, $shippingAddress->getLastname());
        self::assertSame($street, $shippingAddress->getStreet());
        self::assertSame($phone, $shippingAddress->getTelephone());
        self::assertSame($company, $shippingAddress->getCompany());
        self::assertSame($country, $shippingAddress->getCountryId());
        self::assertSame($region, (int) $shippingAddress->getRegionId());
        self::assertSame($postalCode, $shippingAddress->getPostcode());
        self::assertSame($city, $shippingAddress->getCity());
    }
}
