<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class CartFixtureRollback
{
    private $cartRepository;

    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    public static function create(ObjectManagerInterface $objectManager = null)
    {
        if ($objectManager === null) {
            $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        }

        return new self(
            $objectManager->get(CartRepositoryInterface::class)
        );
    }

    public function execute(CartFixture $cartFixture)
    {
        $cart = $this->cartRepository->get($cartFixture->getId());
        $this->cartRepository->delete($cart);
    }
}
