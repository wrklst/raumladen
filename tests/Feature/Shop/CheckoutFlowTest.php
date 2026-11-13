<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaxTreatment;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Orders\MarkOrderPaid;
use App\Payments\BankTransferGateway;
use App\Tax\VatIdValidator;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Coupon mechanics are tested without the fixed-book-price rule; FixedBookPriceTest covers the rule.
    config(['shop.fixed_price_countries' => []]);
    config(['shop.tax.small_business' => false, 'shop.tax.oss_registered' => false]);
    $this->consumer = CustomerGroup::factory()->consumer()->create();
    config(['shop.seller.iban' => 'DE02120300000000202051']);
    Storage::fake('local');
    Mail::fake();
});

function placeOrderPayload(array $extra = [], string $country = 'DE'): array
{
    return [
        'email' => 'kunde@example.com',
        'billing_address' => [
            'first_name' => 'Erika', 'last_name' => 'Mustermann', 'address1' => 'Weg 1',
            'zip' => '10115', 'city' => 'Berlin', 'country' => $country,
        ],
        'gateway' => 'bank_transfer',
        ...$extra,
    ];
}

function submitCheckout(object $test, Product|array $items, array $payload, string $locale = 'de'): TestResponse
{
    $items = $items instanceof Product ? [$items->id => 1] : $items;

    return $test->withSession(['cart.items' => $items])->post(route('shop.checkout.store', ['locale' => $locale]), $payload);
}

test('an empty cart cannot be checked out', function () {
    $this->get(route('shop.checkout.show', ['locale' => 'de']))->assertRedirect(route('shop.cart.index', ['locale' => 'de']));
    $this->post(route('shop.checkout.store', ['locale' => 'de']), placeOrderPayload())
        ->assertRedirect(route('shop.cart.index', ['locale' => 'de']));

    expect(Order::query()->count())->toBe(0);
});

test('the checkout page offers the available gateways and the totals for the chosen country', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => true])]);
    $product = Product::factory()->create(['price_cents' => 2000]);

    $this->withSession(['cart.items' => [$product->id => 1]])
        ->get(route('shop.checkout.show', ['locale' => 'de', 'country' => 'at', 'vat_id' => 'ATU12345678']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shop/checkout')
            ->where('gateways', ['bank_transfer'])
            ->where('cart.treatment', 'intra_community')
            ->where('cart.total_cents', 1869 + 925));
});

test('checkout validation rejects incomplete input', function (array $overrides, string|array $errors) {
    $product = Product::factory()->create();

    submitCheckout($this, $product, [...placeOrderPayload(), ...$overrides])->assertSessionHasErrors($errors);

    expect(Order::query()->count())->toBe(0);
})->with([
    'unknown gateway' => [['gateway' => 'paypal'], 'gateway'],
    'gateway missing' => [['gateway' => null], 'gateway'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'missing email' => [['email' => ''], 'email'],
    'billing address missing' => [['billing_address' => null], ['billing_address.first_name', 'billing_address.country']],
    'country must be two letters' => [['billing_address' => [...placeOrderPayload()['billing_address'], 'country' => 'DEU']], 'billing_address.country'],
]);

test('every billing address field except company and line 2 is required', function (string $field) {
    $product = Product::factory()->create();
    $payload = placeOrderPayload();
    unset($payload['billing_address'][$field]);

    submitCheckout($this, $product, $payload)->assertSessionHasErrors("billing_address.{$field}");
})->with(['first_name', 'last_name', 'address1', 'zip', 'city', 'country']);

test('a bank transfer needs a seller IBAN', function () {
    config(['shop.seller.iban' => '', 'shop.payments.mollie.api_key' => null]);
    $product = Product::factory()->create();

    submitCheckout($this, $product, placeOrderPayload())->assertSessionHasErrors('gateway');
});

test('a shipping address is required only when shipping to a different address', function () {
    $product = Product::factory()->create();

    submitCheckout($this, $product, placeOrderPayload(['ship_to_different' => '1']))
        ->assertSessionHasErrors(['shipping_address.first_name', 'shipping_address.address1', 'shipping_address.country']);
    expect(Order::query()->count())->toBe(0);

    submitCheckout($this, $product, placeOrderPayload(['ship_to_different' => '0']))->assertSessionHasNoErrors();
    expect(Order::query()->count())->toBe(1);
});

test('without a different shipping address the order ships to the billing address', function () {
    $product = Product::factory()->create();

    submitCheckout($this, $product, placeOrderPayload([], 'at'))->assertRedirect();

    $order = Order::query()->sole();

    expect($order->shipping_address)->toBe($order->billing_address)
        ->and($order->billing_address['country'])->toBe('AT')
        ->and($order->country)->toBe('AT');
});

test('the shipping country decides the tax treatment, not the billing country', function (string $billing, string $shipping, TaxTreatment $expected, int $total) {
    $product = Product::factory()->create(['price_cents' => 2000]);

    submitCheckout($this, $product, placeOrderPayload([
        'ship_to_different' => '1',
        'shipping_address' => [...placeOrderPayload()['billing_address'], 'country' => $shipping],
    ], $billing))->assertRedirect();

    $order = Order::query()->sole();

    expect($order->tax_treatment)->toBe($expected)
        ->and($order->country)->toBe(strtoupper($shipping))
        ->and($order->billing_address['country'])->toBe($billing)
        ->and($order->shipping_address['country'])->toBe(strtoupper($shipping))
        ->and($order->total_cents)->toBe($total);
})->with([
    'German billing, Austrian delivery' => ['DE', 'AT', TaxTreatment::DistanceHome, 2990],
    'Austrian billing, German delivery' => ['AT', 'DE', TaxTreatment::Domestic, 2390],
    'German billing, Swiss delivery' => ['DE', 'ch', TaxTreatment::Export, 1869 + 1860],
]);

test('the order records prices, tax groups and items', function () {
    $product = Product::factory()->create(['price_cents' => 2000, 'sku' => 'BK-1']);

    submitCheckout($this, [$product->id => 2], placeOrderPayload(['customer_note' => 'Bitte klingeln']))->assertRedirect();

    $order = Order::query()->with('items')->sole();
    $item = $order->items->sole();

    expect($order->subtotal_net_cents)->toBe(3738)
        ->and($order->shipping_net_cents)->toBe(364)
        ->and($order->shipping_tax_cents)->toBe(26)
        ->and($order->total_cents)->toBe(4390)
        ->and($order->tax_cents)->toBe(262 + 26)
        ->and($order->customer_note)->toBe('Bitte klingeln')
        ->and($order->customer_group_code)->toBe('b2c')
        ->and($order->prices_include_tax)->toBeTrue()
        ->and($order->tax_summary)->toEqual([['rate' => 7.0, 'category' => 'S', 'net' => 3738 + 364, 'tax' => 262 + 26, 'gross' => 4390]])
        ->and($item->only(['product_id', 'sku', 'quantity', 'unit_price_cents', 'line_net_cents', 'line_tax_cents', 'line_gross_cents']))
        ->toBe(['product_id' => $product->id, 'sku' => 'BK-1', 'quantity' => 2, 'unit_price_cents' => 2000, 'line_net_cents' => 3738, 'line_tax_cents' => 262, 'line_gross_cents' => 4000]);
});

test('a business customer is checked out at net prices with tax on top', function () {
    $business = CustomerGroup::factory()->business()->create();
    $user = User::factory()->create(['customer_group_id' => $business->id]);
    $product = Product::factory()->create(['price_cents' => 2000]);

    $this->actingAs($user);
    submitCheckout($this, $product, placeOrderPayload())->assertRedirect();

    $order = Order::query()->sole();

    expect($order->customer_group_code)->toBe('b2b')
        ->and($order->prices_include_tax)->toBeFalse()
        ->and($order->subtotal_net_cents)->toBe(1869)
        ->and($order->total_cents)->toBe(2000 + 389);
});

test('placing an order decrements stock and clears the cart', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $untracked = Product::factory()->create(['stock' => 5, 'track_stock' => false]);

    submitCheckout($this, [$product->id => 2, $untracked->id => 1], placeOrderPayload())
        ->assertRedirect()
        ->assertSessionMissing('cart.items');

    expect($product->refresh()->stock)->toBe(3)
        ->and($untracked->refresh()->stock)->toBe(5);
});

test('an order that no longer fits the stock returns to the cart without creating anything', function () {
    $product = Product::factory()->create(['stock' => 2]);

    submitCheckout($this, [$product->id => 3], placeOrderPayload())
        ->assertRedirect(route('shop.cart.index', ['locale' => 'de']))
        ->assertSessionHasErrors('cart')
        ->assertSessionHas('cart.items', [$product->id => 3]);

    expect(Order::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0)
        ->and($product->refresh()->stock)->toBe(2);
});

test('one sold-out line stops the whole order and leaves other stock alone', function () {
    $available = Product::factory()->create(['stock' => 5]);
    $soldOut = Product::factory()->outOfStock()->create();

    submitCheckout($this, [$available->id => 1, $soldOut->id => 1], placeOrderPayload())->assertSessionHasErrors('cart');

    expect($available->refresh()->stock)->toBe(5)->and(Order::query()->count())->toBe(0);
});

test('a coupon is applied to the order and its use counted', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);
    $coupon = Coupon::factory()->create(['code' => 'TEN', 'value' => 10, 'max_uses' => 5, 'uses' => 1]);

    $this->withSession(['cart.coupon' => 'TEN']);
    submitCheckout($this, $product, placeOrderPayload())->assertRedirect();

    $order = Order::query()->sole();

    expect($order->coupon_id)->toBe($coupon->id)
        ->and($order->coupon_code)->toBe('TEN')
        ->and($order->discount_cents)->toBe(200)
        ->and($order->total_cents)->toBe(1800 + 390)
        ->and($coupon->refresh()->uses)->toBe(2);
});

test('a coupon that has become invalid is neither applied nor counted', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);
    $coupon = Coupon::factory()->expired()->create(['code' => 'OLD']);

    $this->withSession(['cart.coupon' => 'OLD']);
    submitCheckout($this, $product, placeOrderPayload())->assertRedirect();

    $order = Order::query()->sole();

    expect($order->coupon_id)->toBeNull()
        ->and($order->discount_cents)->toBe(0)
        ->and($order->total_cents)->toBe(2390)
        ->and($coupon->refresh()->uses)->toBe(0);
});

test('a guest checkout creates a customer account and remembers the order in the session', function () {
    $product = Product::factory()->create();

    submitCheckout($this, $product, placeOrderPayload(['email' => ' Kunde@Example.COM ']))->assertRedirect();

    $user = User::query()->sole();
    $order = Order::query()->sole();

    expect($user->email)->toBe('kunde@example.com')
        ->and($user->role)->toBe(UserRole::Customer)
        ->and($user->name)->toBe('Erika Mustermann')
        ->and($user->customer_group_id)->toBe($this->consumer->id)
        ->and($user->locale)->toBe('de')
        ->and($user->billing_address['city'])->toBe('Berlin')
        ->and($order->user_id)->toBe($user->id)
        ->and($order->email)->toBe('kunde@example.com')
        ->and($order->locale)->toBe('de');
    $this->assertSame([$order->uuid], session('owned_orders'));
});

test('a guest checkout with a known email reuses the account', function () {
    $existing = User::factory()->create(['email' => 'kunde@example.com']);
    $product = Product::factory()->create();

    submitCheckout($this, $product, placeOrderPayload())->assertRedirect();

    expect(User::query()->count())->toBe(1)->and(Order::query()->sole()->user_id)->toBe($existing->id);
});

test('a signed-in customer keeps their account and gets the new profile data', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user);
    submitCheckout($this, $product, placeOrderPayload(['email' => 'other@example.com']))->assertRedirect();

    expect(User::query()->count())->toBe(1)
        ->and(Order::query()->sole()->user_id)->toBe($user->id)
        ->and($user->refresh()->billing_address['zip'])->toBe('10115');
});

test('the order locale follows the storefront locale', function () {
    $product = Product::factory()->create();

    submitCheckout($this, $product, placeOrderPayload(['ship_to_different' => '0'], 'AT'), 'en')->assertRedirect();

    expect(Order::query()->sole()->locale)->toBe('en');
});

test('order numbers follow the configured gapless sequence', function () {
    $product = Product::factory()->create();

    submitCheckout($this, $product, placeOrderPayload())->assertRedirect();
    submitCheckout($this, $product, placeOrderPayload())->assertRedirect();

    expect(Order::query()->orderBy('id')->pluck('number')->all())->toBe(['B-00001', 'B-00002']);
});

test('the bank transfer payment is started and the customer returns to the payment page', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);

    submitCheckout($this, $product, placeOrderPayload());

    $order = Order::query()->sole();
    $payment = $order->payments()->sole();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($payment->gateway)->toBe('bank_transfer')
        ->and($payment->gateway_id)->toBe($order->number)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount_cents)->toBe(2390);
});

test('an unavailable VIES still places the order with VAT charged', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response('', 503)]);
    $product = Product::factory()->create(['price_cents' => 2000]);

    submitCheckout($this, $product, placeOrderPayload(['vat_id' => 'at u12345678'], 'AT'))->assertRedirect();

    $order = Order::query()->sole();

    expect($order->tax_treatment)->toBe(TaxTreatment::DistanceHome)
        ->and($order->vat_id)->toBe('ATU12345678')
        ->and($order->vat_id_valid)->toBeFalse()
        ->and($order->tax_cents)->toBe(196)
        ->and($order->total_cents)->toBe(2990)
        ->and($order->tax_note)->toBeNull();
});

test('a confirmed VAT ID is stored on the order and the customer profile', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => true])]);
    $product = Product::factory()->create(['price_cents' => 2000]);

    submitCheckout($this, $product, placeOrderPayload(['vat_id' => 'ATU12345678'], 'AT'))->assertRedirect();

    $order = Order::query()->sole();
    $user = User::query()->sole();

    expect($order->vat_id_valid)->toBeTrue()
        ->and($order->vat_id)->toBe('ATU12345678')
        ->and($user->vat_id)->toBe('ATU12345678')
        ->and($user->hasValidVatId())->toBeTrue();
});

test('a failing gateway still leaves the placed order and sends the customer to the payment page', function () {
    Exceptions::fake();
    app()->bind(BankTransferGateway::class, fn () => new class(app(MarkOrderPaid::class)) extends BankTransferGateway
    {
        public function start(Order $order, string $returnUrl, string $webhookUrl): string
        {
            throw new RuntimeException('gateway down');
        }
    });
    $product = Product::factory()->create();

    $response = submitCheckout($this, $product, placeOrderPayload());

    $order = Order::query()->sole();

    $response->assertRedirect(route('shop.payments.return', ['locale' => 'de', 'order' => $order]));
    expect($order->status)->toBe(OrderStatus::Pending);
    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'gateway down');
});
