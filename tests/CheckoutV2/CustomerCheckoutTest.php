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
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     *
     * @throws \Exception
     */
    public function checkoutWithReservedOrderId()
    {
        $cartItemSku = 'test';
        $orderIncrementId = '123456789';

        $this->customerFixture = new CustomerFixture(
            CustomerBuilder::aCustomer()
                ->withAddresses(AddressBuilder::anAddress()->asDefaultShipping()->asDefaultBilling())
                ->build()
        );

        $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($cartItemSku)->build());

        $cart = CartBuilder::forCustomer($this->customerFixture)
            ->withReservedOrderId($orderIncrementId)
            ->withItem($cartItemSku)
            ->build();
        $this->cartFixture = new CartFixture($cart);

        $checkout = CustomerCheckout::withCart($cart);
        $order = $checkout->placeOrder();

        self::assertSame($orderIncrementId, $order->getIncrementId());

        $orderItems = $order->getItems();
        self::assertNotEmpty($orderItems);
        self::assertCount(1, $orderItems);
        foreach ($orderItems as $orderItem) {
            self::assertEquals(1, $orderItem->getQtyOrdered());
            self::assertSame($cartItemSku, $orderItem->getSku());
        }
    }
}
