<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ShippingAddressManagementInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Catalog\ProductFixtureRollback;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\CustomerFixture;
use TddWizard\Fixtures\Customer\CustomerFixtureRollback;

class CartBuilderTest extends TestCase
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

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ShippingAddressManagementInterface
     */
    private $addressManagement;

    protected function setUp()
    {
        $this->cartRepository = Bootstrap::getObjectManager()->create(CartRepositoryInterface::class);
        $this->addressManagement = Bootstrap::getObjectManager()->create(ShippingAddressManagementInterface::class);
    }

    protected function tearDown()
    {
        CartFixtureRollback::create()->execute($this->cartFixture);
        ProductFixtureRollback::create()->execute(...$this->productFixtures);
        CustomerFixtureRollback::create()->execute($this->customerFixture);

        parent::tearDown();
    }

    /**
     * Create a cart with given simple product
     *
     * @test
     * @throws \Exception
     */
    public function createCart()
    {
        $sku = 'test';

        $this->customerFixture = new CustomerFixture(CustomerBuilder::aCustomer()->build());
        $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($sku)->build());
        $this->cartFixture = new CartFixture(
            CartBuilder::forCustomer($this->customerFixture)
                ->withItem($sku)
                ->build()
        );

        $cart = $this->cartRepository->get($this->cartFixture->getId());
        $cartItems = $cart->getItems();
        self::assertNotEmpty($cartItems);
        self::assertCount(1, $cartItems);
        foreach ($cartItems as $cartItem) {
            self::assertSame(1, $cartItem->getQty());
            self::assertSame($sku, $cartItem->getSku());
        }
    }

    /**
     * Create a cart with given simple products and quantities
     *
     * @test
     * @throws \Exception
     */
    public function createCartWithItemQuantities()
    {
        $cartItemData = [
            'foo' => ['qty' => 2],
            'bar' => ['qty' => 3],
        ];

        $this->customerFixture = new CustomerFixture(CustomerBuilder::aCustomer()->build());
        $cartBuilder = CartBuilder::forCustomer($this->customerFixture);

        foreach ($cartItemData as $sku => $data) {
            $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($sku)->build());
            $cartBuilder = $cartBuilder->withItem($sku, $data['qty']);
        }

        $this->cartFixture = new CartFixture($cartBuilder->build());

        $cart = $this->cartRepository->get($this->cartFixture->getId());
        $cartItems = $cart->getItems();
        self::assertCount(count($cartItemData), $cartItems);
        foreach ($cartItems as $cartItem) {
            self::assertSame($cartItemData[$cartItem->getSku()]['qty'], $cartItem->getQty());
        }
    }

    /**
     * Create a cart with given product(s) and given product custom options
     *
     * @test
     * @throws \Exception
     */
    public function createCartWithCustomOptions()
    {
        $cartItemData = [
            'foo' => ['qty' => 2, 'options' => [42 => 'foobar', 303 => 'foxbaz']],
            'bar' => ['qty' => 3, 'options' => []],
        ];

        $this->customerFixture = new CustomerFixture(CustomerBuilder::aCustomer()->build());
        $cartBuilder = CartBuilder::forCustomer($this->customerFixture);

        foreach ($cartItemData as $sku => $data) {
            $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($sku)->build());
            $cartBuilder = $cartBuilder->withItem(
                $sku,
                $data['qty'],
                [CartBuilder::CUSTOM_OPTIONS_KEY => $data['options']]
            );
        }

        $this->cartFixture = new CartFixture($cartBuilder->build());

        $cart = $this->cartRepository->get($this->cartFixture->getId());
        $cartItems = $cart->getItems();
        foreach ($cartItems as $cartItem) {
            $buyRequest = $cartItem->getOptionByCode('info_buyRequest')->getValue();
            foreach ($cartItemData[$cartItem->getSku()]['options'] as $optionId => $optionValue) {
                self::assertContains((string) $optionId, $buyRequest);
                self::assertContains((string) $optionValue, $buyRequest);
            }
        }
    }

    /**
     * Create a cart with one simple product and given address.
     *
     * @todo(nr): move to checkout test
     */
    public function createCartWithAddress()
    {
        $sku = 'test';
        $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($sku)->build());

        $customerBuilder = CustomerBuilder::aCustomer()
            ->withAddresses(
                AddressBuilder::anAddress()
                    ->withFirstname($firstName = 'Wasch')
                    ->withLastname($lastName = 'Bär')
                    ->withStreet($street = ['Trierer Str. 791'])
                    ->withTelephone($phone = '555-666-777')
                    ->withCompany($company = 'integer_net')
                    ->withCountryId($country = 'DE')
                    ->withRegionId($region = 88)
                    ->withPostcode($postalCode = '52078')
                    ->withCity($city = 'Aachen')
                    ->asDefaultBilling()
                    ->asDefaultShipping()
            );

        $this->cartFixture = new CartFixture(
            CartBuilder::aCart()->withCustomer($customerBuilder)->withItem($sku)->build()
        );
        $billingAddress = $this->cartRepository->get($this->cartFixture->getId())->getBillingAddress();
        $shippingAddress = $this->addressManagement->get($this->cartFixture->getId());

        self::assertSame($firstName, $billingAddress->getFirstname());
        self::assertSame($lastName, $billingAddress->getLastname());
        self::assertSame($street, $billingAddress->getStreet());
        self::assertSame($phone, $billingAddress->getTelephone());
        self::assertSame($company, $billingAddress->getCompany());
        self::assertSame($country, $billingAddress->getCountryId());
        self::assertSame($region, (int) $billingAddress->getRegionId());
        self::assertSame($postalCode, $billingAddress->getPostcode());
        self::assertSame($city, $billingAddress->getCity());

        self::assertTrue((bool) $shippingAddress->getSameAsBilling());
    }

    /**
     * Create a cart with one simple product and different addresses for billing/shipping.
     *
     * @todo(nr): move to checkout test
     */
    public function createCartWithAddresses()
    {
        $customerBuilder = CustomerBuilder::aCustomer()
            ->withAddresses(
                AddressBuilder::anAddress()
                    ->withFirstname($billingFirstName = 'Wasch')
                    ->withLastname($billingLastName = 'Bär')
                    ->withStreet($billingStreet = ['Trierer Str. 791'])
                    ->withTelephone($billingPhone = '555-666-777')
                    ->withCompany($billingCompany = 'integer_net')
                    ->withCountryId($billingCountry = 'DE')
                    ->withRegionId($billingRegion = 88)
                    ->withPostcode($billingPostalCode = '52078')
                    ->withCity($billingCity = 'Aachen')
                    ->asDefaultBilling(),
                AddressBuilder::anAddress()
                    ->withFirstname($shippingFirstName = 'Foo')
                    ->withLastname($shippingLastName = 'Bar')
                    ->withStreet($shippingStreet = ['Bahnhofstr. 911'])
                    ->withTelephone($shippingPhone = '111-222-222')
                    ->withCompany($shippingCompany = 'NR')
                    ->withCountryId($shippingCountry = 'DE')
                    ->withRegionId($shippingRegion = 91)
                    ->withPostcode($shippingPostalCode = '04103')
                    ->withCity($shippingCity = 'Leipzig')
                    ->asDefaultShipping()
            );

        $this->cartFixture = new CartFixture(CartBuilder::aCart()->withCustomer($customerBuilder)->build());
        $billingAddress = $this->cartRepository->get($this->cartFixture->getId())->getBillingAddress();
        $shippingAddress = $this->addressManagement->get($this->cartFixture->getId());

        self::assertSame($billingFirstName, $billingAddress->getFirstname());
        self::assertSame($billingLastName, $billingAddress->getLastname());
        self::assertSame($billingStreet, $billingAddress->getStreet());
        self::assertSame($billingPhone, $billingAddress->getTelephone());
        self::assertSame($billingCompany, $billingAddress->getCompany());
        self::assertSame($billingCountry, $billingAddress->getCountryId());
        self::assertSame($billingRegion, (int) $billingAddress->getRegionId());
        self::assertSame($billingPostalCode, $billingAddress->getPostcode());
        self::assertSame($billingCity, $billingAddress->getCity());

        self::assertFalse((bool) $shippingAddress->getSameAsBilling());
        self::assertSame($shippingFirstName, $shippingAddress->getFirstname());
        self::assertSame($shippingLastName, $shippingAddress->getLastname());
        self::assertSame($shippingStreet, $shippingAddress->getStreet());
        self::assertSame($shippingPhone, $shippingAddress->getTelephone());
        self::assertSame($shippingCompany, $shippingAddress->getCompany());
        self::assertSame($shippingCountry, $shippingAddress->getCountryId());
        self::assertSame($shippingRegion, (int) $shippingAddress->getRegionId());
        self::assertSame($shippingPostalCode, $shippingAddress->getPostcode());
        self::assertSame($shippingCity, $shippingAddress->getCity());
    }
}
