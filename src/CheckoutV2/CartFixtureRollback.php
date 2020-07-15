<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartRepositoryInterface;

class CartFixtureRollback
{
    private $registry;

    private $cartRepository;

    public function __construct(Registry $registry, CartRepositoryInterface $cartRepository)
    {
        $this->registry = $registry;
        $this->cartRepository = $cartRepository;
    }

    public static function create(ObjectManagerInterface $objectManager = null)
    {
        if ($objectManager === null) {
            $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        }

        return new self(
            $objectManager->get(Registry::class),
            $objectManager->get(CartRepositoryInterface::class)
        );
    }

    public function execute(CartFixture $cartFixture)
    {
//        $this->registry->unregister('isSecureArea');
//        $this->registry->register('isSecureArea', true);

        $cart = $this->cartRepository->get($cartFixture->getId());

        $this->cartRepository->delete($cart);

//        $this->registry->unregister('isSecureArea');
    }
}
