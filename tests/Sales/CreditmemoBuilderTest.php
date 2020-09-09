<?php

namespace TddWizard\Fixtures\Sales;

use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductBuilder;
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
     * @var CartFixturePool
     */
    private $cartFixtures;

    /**
     * @var OrderFixturePool
     */
    private $orderFixtures;

    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creditmemoRepository = Bootstrap::getObjectManager()->create(CreditmemoRepositoryInterface::class);
        $this->cartFixtures = new CartFixturePool();
        $this->orderFixtures = new OrderFixturePool();
    }

    protected function tearDown(): void
    {
        $this->cartFixtures->rollback();
        $this->orderFixtures->rollback();

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
     * @throws \Exception
     */
    public function createPartialCreditmemos()
    {
        // create customer
        $customer = CustomerBuilder::aCustomer()
            ->withAddresses(AddressBuilder::anAddress()->asDefaultShipping()->asDefaultBilling())
            ->build();

        $order = OrderBuilder::anOrder()->withPaymentMethod('checkmo')->withProducts(
            ProductBuilder::aSimpleProduct()->withSku('foo'),
            ProductBuilder::aSimpleProduct()->withSku('bar')
        )->withCart(
            CartBuilder::forCustomer((int) $customer->getId())->withItem('foo', 2)->withItem('bar', 3)
        )->build();
        $this->orderFixtures->add($order);

        $orderItemIds = [];
        /** @var OrderItemInterface $orderItem */
        foreach ($order->getAllVisibleItems() as $orderItem) {
            $orderItemIds[$orderItem->getSku()] = $orderItem->getItemId();
        }

        $refundFixture = new CreditmemoFixture(
            CreditmemoBuilder::forOrder($order)
                ->withItem($orderItemIds['foo'], 2)
                ->withItem($orderItemIds['bar'], 2)
                ->build()
        );

        self::assertInstanceOf(CreditmemoInterface::class, $this->creditmemoRepository->get($refundFixture->getId()));
        self::assertTrue($order->canCreditmemo());

        $refundFixture = new CreditmemoFixture(
            CreditmemoBuilder::forOrder($order)
                ->withItem($orderItemIds['bar'], 1)
                ->build()
        );

        self::assertInstanceOf(CreditmemoInterface::class, $this->creditmemoRepository->get($refundFixture->getId()));
        self::assertFalse($order->canCreditmemo());
    }
}
