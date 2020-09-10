<?php

namespace TddWizard\Fixtures\Sales;

use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\CheckoutV2\CartBuilder;
use TddWizard\Fixtures\CheckoutV2\CartFixturePool;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class CreditmemoBuilderTest extends TestCase
{
    /**
     * @var ProductFixturePool
     */
    private $productFixtures;

    /**
     * @var OrderFixturePool
     */
    private $orderFixtures;

    /**
     * @var CartFixturePool
     */
    private $cartFixtures;

    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creditmemoRepository = Bootstrap::getObjectManager()->create(CreditmemoRepositoryInterface::class);
        $this->productFixtures = new ProductFixturePool();
        $this->orderFixtures = new OrderFixturePool();
        $this->cartFixtures = new CartFixturePool();
    }

    protected function tearDown(): void
    {
        $this->cartFixtures->rollback();
        $this->orderFixtures->rollback();
        $this->productFixtures->rollback();

        parent::tearDown();
    }

    /**
     * Create a credit memo for all the order's items.
     *
     * @test
     *
     * @throws \Exception
     */
    public function createCreditmemo()
    {
        $order = OrderBuilder::anOrder()->withPaymentMethod('checkmo')->build();
        $this->orderFixtures->add($order);

        $refundFixture = new CreditmemoFixture(CreditmemoBuilder::forOrder($order)->build());

        self::assertInstanceOf(CreditmemoInterface::class, $this->creditmemoRepository->get($refundFixture->getId()));
        self::assertFalse($order->canCreditmemo());
    }

    /**
     * Create a credit memo for some of the order's items.
     *
     * @test
     * @magentoConfigFixture default_store payment/fake/active 0
     * @magentoConfigFixture default_store payment/fake_vault/active 0
     * @throws \Exception
     */
    public function createPartialCreditmemos()
    {
        // create products
        $fooProduct = ProductBuilder::aSimpleProduct()->withSku('foo')->build();
        $barProduct = ProductBuilder::aSimpleProduct()->withSku('bar')->build();

        // create customer
        $customer = CustomerBuilder::aCustomer()
            ->withAddresses(AddressBuilder::anAddress()->asDefaultShipping()->asDefaultBilling())
            ->build();

        $cart = CartBuilder::forCustomer((int) $customer->getId())
            ->withItem($fooProduct->getSku(), 2)
            ->withItem($barProduct->getSku(), 3)
            ->build();

        $order = OrderBuilder::anOrder()
        //            ->withPaymentMethod('checkmo')
            ->withProducts($fooProduct, $barProduct)
            ->withCart($cart)
            ->build();

        $this->productFixtures->add($fooProduct);
        $this->productFixtures->add($barProduct);
        $this->cartFixtures->add($cart);
        $this->orderFixtures->add($order);

        $orderItemIds = [];
        /** @var OrderItemInterface $orderItem */
        foreach ($order->getAllVisibleItems() as $orderItem) {
            $orderItemIds[$orderItem->getSku()] = $orderItem->getItemId();
        }

        $refund = CreditmemoBuilder::forOrder($order)
            ->withItem($orderItemIds[$fooProduct->getSku()], 2)
            ->withItem($orderItemIds[$barProduct->getSku()], 2)
            ->build();

        self::assertInstanceOf(CreditmemoInterface::class, $this->creditmemoRepository->get($refund->getEntityId()));
        self::assertTrue($order->canCreditmemo());

        $refund = CreditmemoBuilder::forOrder($order)
            ->withItem($orderItemIds[$barProduct->getSku()], 1)
            ->build();

        self::assertInstanceOf(CreditmemoInterface::class, $this->creditmemoRepository->get($refund->getEntityId()));
        self::assertFalse($order->canCreditmemo());
    }
}
