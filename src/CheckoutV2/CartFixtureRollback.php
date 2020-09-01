<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;

class CartFixtureRollback
{
    private $cartRepository;

    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    public static function create()
    {
        return new self(Bootstrap::getObjectManager()->get(CartRepositoryInterface::class));
    }

    public function execute(CartFixture ...$cartFixtures)
    {
        foreach ($cartFixtures as $cartFixture) {
            $cart = $this->cartRepository->get($cartFixture->getId());
            $this->cartRepository->delete($cart);
        }
    }
}
