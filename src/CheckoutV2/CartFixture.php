<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Quote\Api\Data\CartInterface;

class CartFixture
{
    /**
     * @var CartInterface
     */
    private $cart;

    public function __construct(CartInterface $cart)
    {
        $this->cart = $cart;
    }

    public function getId() : int
    {
        return $this->cart->getId();
    }
}
