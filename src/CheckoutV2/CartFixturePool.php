<?php
declare(strict_types=1);

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Quote\Api\Data\CartInterface;

class CartFixturePool
{
    /**
     * @var CartFixture[]
     */
    private $cartFixtures = [];

    public function add(CartInterface $cart, string $key = null): void
    {
        if ($key === null) {
            $this->cartFixtures[] = new CartFixture($cart);
        } else {
            $this->cartFixtures[$key] = new CartFixture($cart);
        }
    }

    /**
     * Returns cart fixture by key, or last added if key not specified
     *
     * @param string|null $key
     * @return CartFixture
     */
    public function get(string $key = null): CartFixture
    {
        if ($key === null) {
            $key = \array_key_last($this->cartFixtures);
        }
        if (!array_key_exists($key, $this->cartFixtures)) {
            throw new \OutOfBoundsException('No matching cart found in fixture pool');
        }
        return $this->cartFixtures[$key];
    }

    public function rollback(): void
    {
        CartFixtureRollback::create()->execute(...$this->cartFixtures);
        $this->cartFixtures = [];
    }
}
