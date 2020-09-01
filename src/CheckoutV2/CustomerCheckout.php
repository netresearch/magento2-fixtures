<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\BillingAddressManagementInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Quote\Model\ResourceModel\Quote\Address\Rate\CollectionFactory;
use Magento\Quote\Model\ShippingAddressManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;

class CustomerCheckout
{
    /**
     * @var CartInterface
     */
    private $cart;

    /**
     * @var ShippingInformationManagementInterface
     */
    private $shippingManagement;

    /**
     * @var ShippingAddressManagementInterface
     */
    private $shippingAddressManagement;

    /**
     * @var ShippingMethodManagementInterface
     */
    private $shippingMethodManagement;

    /**
     * @var PaymentInformationManagementInterface
     */
    private $paymentManagement;

    /**
     * @var BillingAddressManagementInterface
     */
    private $billingAddressManagement;

    /**
     * @var PaymentMethodManagementInterface
     */
    private $paymentMethodManagement;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var CollectionFactory
     */
    private $rateCollectionFactory;

    /**
     * @var ShippingInformationInterfaceFactory
     */
    private $shippingFactory;

    /**
     * @var PaymentInterfaceFactory
     */
    private $paymentFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        CartInterface $cart,
        ShippingInformationManagementInterface $shippingManagement,
        ShippingAddressManagementInterface $shippingAddressManagement,
        ShippingMethodManagementInterface $shippingMethodManagement,
        PaymentInformationManagementInterface $paymentManagement,
        BillingAddressManagementInterface $billingAddressManagement,
        PaymentMethodManagementInterface $paymentMethodManagement,
        CartManagementInterface $cartManagement,
        CollectionFactory $rateCollectionFactory,
        ShippingInformationInterfaceFactory $shippingFactory,
        PaymentInterfaceFactory $paymentFactory,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->cart = $cart;
        $this->shippingManagement = $shippingManagement;
        $this->shippingAddressManagement = $shippingAddressManagement;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->paymentManagement = $paymentManagement;
        $this->billingAddressManagement = $billingAddressManagement;
        $this->paymentMethodManagement = $paymentMethodManagement;
        $this->cartManagement = $cartManagement;
        $this->rateCollectionFactory = $rateCollectionFactory;
        $this->shippingFactory = $shippingFactory;
        $this->paymentFactory = $paymentFactory;
        $this->orderRepository = $orderRepository;
    }

    public static function withCart(CartInterface $cart): CustomerCheckout
    {
        $objectManager = Bootstrap::getObjectManager();

        return new static(
            $cart,
            $objectManager->create(ShippingInformationManagementInterface::class),
            $objectManager->create(ShippingAddressManagementInterface::class),
            $objectManager->create(ShippingMethodManagementInterface::class),
            $objectManager->create(PaymentInformationManagementInterface::class),
            $objectManager->create(BillingAddressManagementInterface::class),
            $objectManager->create(PaymentMethodManagementInterface::class),
            $objectManager->create(CartManagementInterface::class),
            $objectManager->create(CollectionFactory::class),
            $objectManager->create(ShippingInformationInterfaceFactory::class),
            $objectManager->create(PaymentInterfaceFactory::class),
            $objectManager->create(OrderRepositoryInterface::class)
        );
    }

    /**
     * Retrieve billing address.
     *
     * Return the address specified by given ID.
     * - If not found, use default billing address.
     * - If not found, use any address.
     *
     * @param int|null $customerAddressId
     * @param string|null $fallbackType Enum, "default_billing" or "default_shipping"
     * @return AddressInterface
     * @throws NoSuchEntityException
     */
    private function getAddress(int $customerAddressId = null, string $fallbackType = null): AddressInterface
    {
        $customerAddresses = $this->cart->getCustomer()->getAddresses();
        if (empty($customerAddresses)) {
            throw new NoSuchEntityException(__('Customer checkout requires a customer address.'));
        }

        $requestedAddress = null;
        $defaultAddress = null;

        foreach ($customerAddresses as $customerAddress) {
            if ($fallbackType === AddressInterface::DEFAULT_BILLING && $customerAddress->isDefaultBilling()) {
                $defaultAddress = $customerAddress;
            }

            if ($fallbackType === AddressInterface::DEFAULT_SHIPPING && $customerAddress->isDefaultShipping()) {
                $defaultAddress = $customerAddress;
            }

            if ((int) $customerAddress->getId() === $customerAddressId) {
                $requestedAddress = $customerAddress;
            }
        }

        $address = $requestedAddress ?: $defaultAddress;
        return $address ?: array_shift($customerAddresses);
    }

    /**
     * Submit shipping information step with given shipping method and optional customer address ID.
     *
     * If shipping method is not given, first available method will be used.
     * If customer address ID is not given, then address book is used, preferably default shipping address.
     *
     * @param string|null $shippingMethod
     * @param int|null $customerAddressId
     * @throws LocalizedException
     */
    public function submitShipping(string $shippingMethod = null, int $customerAddressId = null)
    {
        $customerAddress = $this->getAddress($customerAddressId, AddressInterface::DEFAULT_SHIPPING);
        $shippingAddress = $this->cart->getShippingAddress()->importCustomerAddressData($customerAddress);
        $this->shippingAddressManagement->assign($this->cart->getId(), $shippingAddress);

        if (!$shippingMethod) {
            $shippingMethods = $this->shippingMethodManagement->getList($this->cart->getId());
            $carrierCode = $shippingMethods[0]->getCarrierCode();
            $methodCode = $shippingMethods[0]->getMethodCode();
        } else {
            list($carrierCode, $methodCode) = explode('_', $shippingMethod);
        }

        $shippingInformation = $this->shippingFactory->create();
        $shippingInformation->setShippingCarrierCode($carrierCode);
        $shippingInformation->setShippingMethodCode($methodCode);
        $shippingInformation->setShippingAddress($shippingAddress);

        $this->shippingManagement->saveAddressInformation($this->cart->getId(), $shippingInformation);
    }

    /**
     * Submit payment information step with given payment method and optional customer address ID.
     *
     * If customer address ID is not given, then address book is used, preferably default billing address.
     *
     * @param string|null $paymentMethod
     * @param int|null $customerAddressId
     * @throws LocalizedException
     */
    public function submitPayment(string $paymentMethod = null, int $customerAddressId = null)
    {
        $customerAddress = $this->getAddress($customerAddressId, AddressInterface::DEFAULT_BILLING);
        $billingAddress = $this->cart->getBillingAddress()->importCustomerAddressData($customerAddress);
        $this->billingAddressManagement->assign($this->cart->getId(), $billingAddress);

        if (!$paymentMethod) {
            $paymentMethods = $this->paymentMethodManagement->getList($this->cart->getId());
            $paymentMethod = $paymentMethods[0]->getCode();
        }

        $payment = $this->paymentFactory->create();
        $payment->setMethod($paymentMethod);

        $this->paymentManagement->savePaymentInformation($this->cart->getId(), $payment, $billingAddress);
    }

    /**
     * @return OrderInterface
     * @throws LocalizedException
     */
    public function placeOrder()
    {
        $shippingMethod = $this->cart->getShippingAddress()->getShippingMethod();
        if (empty($shippingMethod)) {
            $this->submitShipping();
        }

        $paymentMethod = $this->paymentMethodManagement->get($this->cart->getId());
        if (!$paymentMethod) {
            $this->submitPayment();
        }

        $orderId = $this->cartManagement->placeOrder($this->cart->getId());
        return $this->orderRepository->get($orderId);
    }
}
