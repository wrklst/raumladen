<?php

use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Coupon mechanics are tested without the fixed-book-price rule; FixedBookPriceTest covers the rule.
    config(['shop.fixed_price_countries' => []]);
    CustomerGroup::factory()->consumer()->create();
});

function cartUrl(string $name = 'shop.cart.store', array $parameters = []): string
{
    return route($name, ['locale' => 'de', ...$parameters]);
}

test('adding a product puts it in the session cart and accumulates quantities', function () {
    $product = Product::factory()->create();

    $this->post(cartUrl(), ['product_id' => $product->id, 'quantity' => 2])
        ->assertRedirect(cartUrl('shop.cart.index'))
        ->assertSessionHas('cart.items', [$product->id => 2]);

    $this->post(cartUrl(), ['product_id' => $product->id])
        ->assertSessionHas('cart.items', [$product->id => 3]);
});

test('adding validates the product and quantity', function (array $payload) {
    $this->post(cartUrl(), $payload)->assertSessionHasErrors();
})->with([
    'missing product' => [[]],
    'quantity zero' => [['product_id' => 1, 'quantity' => 0]],
    'quantity above 99' => [['product_id' => 1, 'quantity' => 100]],
    'fractional quantity' => [['product_id' => 1, 'quantity' => 1.5]],
]);

test('inactive, unpublished and unknown products cannot be added', function () {
    $inactive = Product::factory()->inactive()->create();
    $scheduled = Product::factory()->create(['published_at' => now()->addDay()]);

    $this->post(cartUrl(), ['product_id' => $inactive->id])->assertNotFound();
    $this->post(cartUrl(), ['product_id' => $scheduled->id])->assertNotFound();
    $this->post(cartUrl(), ['product_id' => 99999])->assertNotFound();
    $this->assertNull(session('cart.items'));
});

test('the quantity in the cart is limited by the stock', function () {
    $product = Product::factory()->create(['stock' => 3]);

    $this->post(cartUrl(), ['product_id' => $product->id, 'quantity' => 4])
        ->assertSessionHasErrors('quantity');
    $this->assertNull(session('cart.items'));

    $this->post(cartUrl(), ['product_id' => $product->id, 'quantity' => 2])->assertSessionHasNoErrors();
    $this->post(cartUrl(), ['product_id' => $product->id, 'quantity' => 2])->assertSessionHasErrors('quantity');
    $this->assertSame([$product->id => 2], session('cart.items'));
});

test('products without stock tracking can always be added', function () {
    $product = Product::factory()->create(['stock' => 0, 'track_stock' => false]);

    $this->post(cartUrl(), ['product_id' => $product->id, 'quantity' => 5])
        ->assertSessionHas('cart.items', [$product->id => 5]);
});

test('updating sets the quantity and zero removes the line', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $other = Product::factory()->create();

    $this->withSession(['cart.items' => [$product->id => 1, $other->id => 1]])
        ->patch(cartUrl('shop.cart.update', ['product' => $product->id]), ['quantity' => 4])
        ->assertSessionHas('cart.items', [$product->id => 4, $other->id => 1]);

    $this->patch(cartUrl('shop.cart.update', ['product' => $product->id]), ['quantity' => 0])
        ->assertSessionHas('cart.items', [$other->id => 1]);
});

test('updating beyond the stock or the limit is rejected', function (int $quantity) {
    $product = Product::factory()->create(['stock' => 5]);

    $this->withSession(['cart.items' => [$product->id => 1]])
        ->patch(cartUrl('shop.cart.update', ['product' => $product->id]), ['quantity' => $quantity])
        ->assertSessionHasErrors('quantity');

    $this->assertSame([$product->id => 1], session('cart.items'));
})->with([
    'more than in stock' => [6],
    'more than 99' => [100],
    'negative' => [-1],
]);

test('removing a line leaves the others untouched', function () {
    $product = Product::factory()->create();
    $other = Product::factory()->create();

    $this->withSession(['cart.items' => [$product->id => 2, $other->id => 1]])
        ->delete(cartUrl('shop.cart.destroy', ['product' => $product->id]))
        ->assertSessionHas('cart.items', [$other->id => 1]);
});

test('the cart page drops inactive and vanished products', function () {
    $active = Product::factory()->create(['price_cents' => 2000]);
    $inactive = Product::factory()->inactive()->create();
    $deleted = Product::factory()->create();
    $deleted->delete();

    $this->withSession(['cart.items' => [$active->id => 1, $inactive->id => 1, $deleted->id => 1, 99999 => 1]])
        ->get(cartUrl('shop.cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shop/cart')
            ->has('cart.lines', 1)
            ->where('cart.lines.0.product_id', $active->id)
            ->where('cart.total_cents', 2390));

    $this->assertSame([$active->id => 1], session('cart.items'));
});

test('an empty cart renders with zero totals', function () {
    $this->get(cartUrl('shop.cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('cart.lines', 0)->where('cart.total_cents', 0));
});

test('a known coupon is stored under its canonical code', function () {
    Coupon::factory()->create(['code' => 'SPRING10']);

    $this->post(cartUrl('shop.cart.coupon.store'), ['code' => ' spring10'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('cart.coupon', 'SPRING10');
});

test('an unknown coupon is rejected and not stored', function () {
    $this->post(cartUrl('shop.cart.coupon.store'), ['code' => 'NOPE'])
        ->assertSessionHasErrors('code');

    $this->assertNull(session('cart.coupon'));
});

test('a coupon code is required', function () {
    $this->post(cartUrl('shop.cart.coupon.store'), ['code' => ''])->assertSessionHasErrors('code');
});

test('a coupon can be removed again', function () {
    $this->withSession(['cart.coupon' => 'SPRING10'])
        ->delete(cartUrl('shop.cart.coupon.destroy'))
        ->assertSessionMissing('cart.coupon');
});

test('the cart page applies a stored coupon or explains why it does not apply', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);
    Coupon::factory()->create(['code' => 'TEN', 'value' => 10]);
    Coupon::factory()->create(['code' => 'BIG', 'min_order_cents' => 10000]);

    $this->withSession(['cart.items' => [$product->id => 1], 'cart.coupon' => 'TEN'])
        ->get(cartUrl('shop.cart.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cart.coupon_code', 'TEN')
            ->where('cart.discount_cents', 200)
            ->where('cart.coupon_error', null));

    $this->withSession(['cart.items' => [$product->id => 1], 'cart.coupon' => 'BIG'])
        ->get(cartUrl('shop.cart.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cart.coupon_code', null)
            ->where('cart.discount_cents', 0)
            ->where('cart.coupon_error', 'below_minimum'));
});
