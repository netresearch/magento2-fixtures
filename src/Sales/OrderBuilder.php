<?php
declare(strict_types=1);

namespace TddWizard\Fixtures\Sales;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\CheckoutV2\CartBuilder;
use TddWizard\Fixtures\CheckoutV2\CustomerCheckout;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;

/**
 * Builder to be used by fixtures
 */
class OrderBuilder
{
    /**
     * @var ProductInterface[]
     */
    private $products;

    /**
     * @var CustomerInterface
     */
    private $customer;

    /**
     * @var CartInterface
     */
    private $cart;

    /**
     * @var string
     */
    private $shippingMethod;

    final public function __construct()
    {
    }

    /**
     * @var string
     */
    private $paymentMethod;

    public static function anOrder(): OrderBuilder
    {
        return new static();
    }

    public function withProducts(ProductInterface ...$products): OrderBuilder
    {
        $builder = clone $this;
        $builder->products = $products;

        return $builder;
    }

    public function withCustomer(CustomerInterface $customer): OrderBuilder
    {
        $builder = clone $this;
        $builder->customer = $customer;

        return $builder;
    }

    public function withCart(CartInterface $cart): OrderBuilder
    {
        $builder = clone $this;
        $builder->cart = $cart;

        return $builder;
    }

    public function withShippingMethod(string $shippingMethod): OrderBuilder
    {
        $builder = clone $this;
        $builder->shippingMethod = $shippingMethod;

        return $builder;
    }

    public function withPaymentMethod(string $paymentMethod): OrderBuilder
    {
        $builder = clone $this;
        $builder->paymentMethod = $paymentMethod;

        return $builder;
    }

    /**
     * @return OrderInterface|Order
     * @throws \Exception
     */
    public function build(): OrderInterface
    {
        $builder = clone $this;

        if (empty($builder->products)) {
            // init simple products
            for ($i = 0; $i < 3; $i++) {
                $builder->products[] = ProductBuilder::aSimpleProduct()->build();
            }
        }

        if (empty($builder->customer)) {
            // init customer
            $builder->customer = CustomerBuilder::aCustomer()
                ->withAddresses(AddressBuilder::anAddress()->asDefaultBilling()->asDefaultShipping())
                ->build();
        }

        if (empty($builder->cart)) {
            // init cart, add products
            $cartBuilder = CartBuilder::forCustomer((int) $builder->customer->getId());
            foreach ($builder->products as $product) {
                $cartBuilder = $cartBuilder->withItem($product->getSku());
            }

            $builder->cart = $cartBuilder->build();
        }

        // check out, place order
        $checkout = CustomerCheckout::withCart($builder->cart);
        if ($builder->shippingMethod) {
            $checkout->submitShipping($builder->shippingMethod);
        }

        if ($builder->paymentMethod) {
            $checkout->submitPayment($builder->paymentMethod);
        }

        return $checkout->placeOrder();
    }
}
