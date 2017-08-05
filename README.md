# TddWizard Fixture library

*Preview release 2017-08-02*

This library is in *pre-alpha* state, that means:

- it's super incomplete
- nothing is guaranteed to work
- everything can still be changed

But since it already greatly improved our own tests and there is high demand, we are excited to share it as preview. A real public repository will follow soon.

---

## What is it?

An alternative to the procedural script based fixtures in Magento 2 integration tests.

It aims to be:

- extensible
- expressive
- easy to use

## Usage examples:

### Customer

If you need a customer without specific data, this is all:

```php
protected function setUp()
{
  $this->customerFixture = new CustomerFixture(
    CustomerBuilder::aCustomer()->build()
  );
}
protected function tearDown()
{
  CustomerFixtureRollback::create()->execute($this->customerFixture);
}
```

It uses default sample data and a random email address. If you need the ID or email address in the tests, the `CustomerFixture` gives you access:

```php
$this->customerFixture->getId();
$this->customerFixture->getEmail();
```

You can configure the builder with attributes:

```php
CustomerBuilder::aCustomer()
  ->withEmail('test@example.com')
  ->withCustomAttributes(
    [
      'my_custom_attribute' => 42
    ]
  )
  ->build()
```

You can add addresses to the customer:

```php
CustomerBuilder::aCustomer()
  ->withAddresses(
    AddressBuilder::anAddress()->asDefaultBilling(),
    AddressBuilder::anAddress()->asDefaultShipping(),
    AddressBuilder::anAddress()
  )
  ->build()
```

Or just one:

```php
CustomerBuilder::aCustomer()
  ->withAddresses(
    AddressBuilder::anAddress()->asDefaultBilling()->asDefaultShipping()
  )
  ->build()
```

The `CustomerFixture` also has a shortcut to create a customer session:

```php
$this->customerFixture->login();
```



### Adresses

Similar to the customer builder you can also configure the address builder with custom attributes:

```php
AddressBuilder::anAddress()
  ->withCountryId('DE')
  ->withCity('Aachen')
  ->withPostcode('52078')
  ->withCustomAttributes(
    [
      'my_custom_attribute' => 42
    ]
  )
  ->asDefaultShipping()
```

### Product

Product fixtures work similar as customer fixtures:

```php
protected function setUp()
{
  $this->productFixture = new ProductFixture(
    ProductBuilder::aSimpleProduct()
      ->withPrice(10)
      ->withCustomAttributes(
        [
          'my_custom_attribute' => 42
        ]
      )
      ->build()
  );
}
protected function tearDown()
{
  ProductFixtureRollback::create()->execute($this->productFixture);
}
```

The SKU is randomly generated and can be accessed through `ProductFixture`, just as the ID:

```php
$this->productFixture->getSku();
$this->productFixture->getId();
```

### Cart/Checkout

To create a quote, use the `CartBuilder` together with product fixtures:

```php
$cart = CartBuilder::forCurrentSession()
  ->withSimpleProduct(
    $productFixture1->getSku()
  )
  ->withSimpleProduct(
    $productFixture2->getSku(), 10 // optional qty parameter
  )
  ->build()
$quote = $cart->getQuote();
```

Checkout is supported for logged in customers. To create an order, you can simulate the checkout as follows, given a customer fixture with default shipping and billing addresses and a product fixture:

```php
$this->customerFixture->login();
$checkout = CustomerCheckout::fromCart(
  CartBuilder::forCurrentSession()
    ->withSimpleProduct(
      $productFixture->getSku()
    )
    ->build()
);
$order = $checkout->placeOrder();

```

It will try to select the default addresses and the first available shipping and payment methods.

You can also select them explicitly:

```php
$order = $checkout
  ->withShippingMethodCode('freeshipping_freeshipping')
  ->withPaymentMethodCode('checkmo')
  ->withCustomerBillingAddressId($this->customerFixture->getOtherAddressId())
  ->withCustomerShippingAddressId($this->customerFixture->getOtherAddressId())
  ->placeOrder();
```

