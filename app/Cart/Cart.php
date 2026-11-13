<?php

namespace App\Cart;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Session cart: product id => quantity, plus an optional coupon code.
 */
class Cart
{
    private const string KEY = 'cart.items';

    private const string COUPON_KEY = 'cart.coupon';

    public function __construct(private Session $session) {}

    /**
     * @return array<int, int>
     */
    public function items(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $items = $this->items();
        $items[$product->id] = ($items[$product->id] ?? 0) + $quantity;
        $this->session->put(self::KEY, $items);
    }

    public function update(int $productId, int $quantity): void
    {
        $items = $this->items();

        if ($quantity <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $quantity;
        }

        $this->session->put(self::KEY, $items);
    }

    public function remove(int $productId): void
    {
        $this->update($productId, 0);
    }

    public function clear(): void
    {
        $this->session->forget([self::KEY, self::COUPON_KEY]);
    }

    public function count(): int
    {
        return array_sum($this->items());
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    public function couponCode(): ?string
    {
        return $this->session->get(self::COUPON_KEY);
    }

    public function setCouponCode(?string $code): void
    {
        $code === null
            ? $this->session->forget(self::COUPON_KEY)
            : $this->session->put(self::COUPON_KEY, $code);
    }

    /**
     * Active products in the cart with their quantities. Vanished products are dropped.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        $items = $this->items();

        if ($items === []) {
            return collect();
        }

        $products = Product::query()->active()->with('prices')->whereKey(array_keys($items))->get()->keyBy('id');

        $kept = array_intersect_key($items, $products->all());

        if (count($kept) !== count($items)) {
            $this->session->put(self::KEY, $kept);
        }

        return collect($kept)
            ->map(fn (int $quantity, int $id) => new CartLine($products[$id], $quantity))
            ->values();
    }
}
