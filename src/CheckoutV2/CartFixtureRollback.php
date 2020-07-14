<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartRepositoryInterface;

class CartFixtureRollback
{
    private $registry;

    private $cartRepository;

    private $customerRepository;

    public function __construct(
        Registry $registry,
        CartRepositoryInterface $cartRepository,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->registry = $registry;
        $this->cartRepository = $cartRepository;
        $this->customerRepository = $customerRepository;
    }

    public static function create(ObjectManagerInterface $objectManager = null)
    {
        if ($objectManager === null) {
            $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        }

        return new self(
            $objectManager->get(Registry::class),
            $objectManager->get(CartRepositoryInterface::class),
            $objectManager->get(CustomerRepositoryInterface::class)
        );
    }

    public function execute(CartFixture $cartFixture)
    {
        $this->registry->unregister('isSecureArea');
        $this->registry->register('isSecureArea', true);

        $cart = $this->cartRepository->get($cartFixture->getId());
        $customerId = $cart->getCustomer()->getId();

        $this->cartRepository->delete($cart);
        $this->customerRepository->deleteById($customerId);

        $this->registry->unregister('isSecureArea');
    }
}
