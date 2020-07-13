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

        parent::tearDown();
    }

    /**
     * Create a cart with a simple product and random address.
     *
     * @test
     */
    public function createCart()
    {
        $this->cartFixture = new CartFixture(CartBuilder::aCart()->build());
        $cart = $this->cartRepository->get($this->cartFixture->getId());
        $cartItems = $cart->getItems();
        self::assertNotEmpty($cartItems);
        self::assertCount(1, $cartItems);
        foreach ($cartItems as $cartItem) {
            self::assertSame(1, $cartItem->getQty());
        }
    }

    /**
     * Create a cart with one simple product and given address.
     *
     * @test
     */
    public function createCartWithAddress()
    {
        $customerBuilder = CustomerBuilder::aCustomer()
            ->withAddresses(
                AddressBuilder::anAddress()
                    ->withFirstname($firstName = 'Wasch')
                    ->withLastname($lastName = 'Bär')
                    ->withStreet($street = 'Trierer Str. 791')
                    ->withTelephone($phone = '555-666-777')
                    ->withCompany($company = 'integer_net')
                    ->withCountryId($country = 'DE')
                    ->withRegionId($region = 88)
                    ->withPostcode($postalCode = '52078')
                    ->withCity($city = 'Aachen')
                    ->asDefaultBilling()
                    ->asDefaultShipping()
            );

        $this->cartFixture = new CartFixture(CartBuilder::aCart()->withCustomer($customerBuilder)->build());
        $billingAddress = $this->cartRepository->get($this->cartFixture->getId())->getBillingAddress();
        $shippingAddress = $this->addressManagement->get($this->cartFixture->getId());

        self::assertSame($firstName, $billingAddress->getFirstname());
        self::assertSame($lastName, $billingAddress->getLastname());
        self::assertSame($street, $billingAddress->getStreet());
        self::assertSame($phone, $billingAddress->getTelephone());
        self::assertSame($company, $billingAddress->getCompany());
        self::assertSame($country, $billingAddress->getCountryId());
        self::assertSame($region, $billingAddress->getRegionId());
        self::assertSame($postalCode, $billingAddress->getPostcode());
        self::assertSame($city, $billingAddress->getCity());

        self::assertTrue($shippingAddress->getSameAsBilling());
    }

    /**
     * Create a cart with one simple product and different addresses for billing/shipping.
     *
     * @test
     */
    public function createCartWithAddresses()
    {
        $customerBuilder = CustomerBuilder::aCustomer()
            ->withAddresses(
                AddressBuilder::anAddress()
                    ->withFirstname($billingFirstName = 'Wasch')
                    ->withLastname($billingLastName = 'Bär')
                    ->withStreet($billingStreet = 'Trierer Str. 791')
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
                    ->withStreet($shippingStreet = 'Bahnhofstr. 911')
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
        self::assertSame($billingRegion, $billingAddress->getRegionId());
        self::assertSame($billingPostalCode, $billingAddress->getPostcode());
        self::assertSame($billingCity, $billingAddress->getCity());

        self::assertFalse($shippingAddress->getSameAsBilling());
        self::assertSame($shippingFirstName, $shippingAddress->getFirstname());
        self::assertSame($shippingLastName, $shippingAddress->getLastname());
        self::assertSame($shippingStreet, $shippingAddress->getStreet());
        self::assertSame($shippingPhone, $shippingAddress->getTelephone());
        self::assertSame($shippingCompany, $shippingAddress->getCompany());
        self::assertSame($shippingCountry, $shippingAddress->getCountryId());
        self::assertSame($shippingRegion, $shippingAddress->getRegionId());
        self::assertSame($shippingPostalCode, $shippingAddress->getPostcode());
        self::assertSame($shippingCity, $shippingAddress->getCity());
    }

    /**
     * Create a cart with given product(s)
     *
     * @test
     */
    public function createCartWithProduct()
    {
        $cartItemData = [
            'foo' => ['qty' => 2],
            'bar' => ['qty' => 3],
        ];

        $cart = CartBuilder::aCart();
        foreach ($cartItemData as $sku => $cartItem) {
            // create product in catalog
            $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($sku)->build());

            // add item data to cart builder
            $cart->withItem($sku, $cartItem['qty']);
        }

        $this->cartFixture = new CartFixture($cart->build());
        $cart = $this->cartRepository->get($this->cartFixture->getId());
        $cartItems = $cart->getItems();
        self::assertCount(count($cartItemData), $cartItems);
        foreach ($cartItems as $cartItem) {
            self::assertSame(1, $cartItem->getQty());
            self::assertSame($cartItemData[$cartItem->getSku()]['qty'], $cartItem->getQty());
        }
    }

    /**
     * Create a cart with given product(s) and given product custom options
     *
     * @test
     */
    public function createCartWithProductOptions()
    {
        $cartItemData = [
            'foo' => ['qty' => 2, 'options' => [42 => 'foobar', 303 => 'foxbaz']],
            'bar' => ['qty' => 3, 'options' => []],
        ];

        $cart = CartBuilder::aCart();
        foreach ($cartItemData as $sku => $cartItem) {
            // create product in catalog
            $this->productFixtures[] = new ProductFixture(ProductBuilder::aSimpleProduct()->withSku($sku)->build());

            // add item data to cart builder
            $productOptions = empty($cartItem['options']) ? [] : ['custom_options' => $cartItem['options']];
            $cart->withItem($sku, $cartItem['qty'], $productOptions);
        }

        $this->cartFixture = new CartFixture($cart->build());
        $cart = $this->cartRepository->get($this->cartFixture->getId());
        $cartItems = $cart->getItems();

        //todo(nr): assert that items have product options
    }
}
