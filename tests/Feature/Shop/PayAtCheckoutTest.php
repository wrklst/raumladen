<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderConfirmation;
use App\Mail\OrderReceived;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\OnlineGateway;

/*
 * Paying online: the customer stays at the checkout, and the order is placed
 * (listed, confirmed by mail, the cart emptied) when the payment is confirmed.
 */
beforeEach(function () {
    config(['shop.fixed_price_countries' => [], 'shop.tax.small_business' => false, 'shop.tax.oss_registered' => false]);
    config(['shop.payments.gateways.online' => OnlineGateway::class]);
    CustomerGroup::factory()->consumer()->create();
    Storage::fake('local');
    Mail::fake();
    OnlineGateway::$outcome = PaymentStatus::Open;
    OnlineGateway::$started = [];
    OnlineGateway::$refunded = [];
    $this->product = Product::factory()->create(['price_cents' => 2000, 'stock' => 5]);
});

function payOnline(object $test, array $extra = []): TestResponse
{
    return $test->withSession(['cart.items' => [$test->product->id => 1]])
        ->from(route('shop.checkout.show', ['locale' => 'de']))
        ->post(route('shop.checkout.store', ['locale' => 'de']), [
            'email' => 'kunde@example.com',
            'billing_address' => ['first_name' => 'Erika', 'last_name' => 'Mustermann', 'address1' => 'Weg 1', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE'],
            'gateway' => 'online',
            ...$extra,
        ]);
}

test('paying online keeps the customer at the checkout: no mail, the cart kept, the order not yet placed', function () {
    $response = payOnline($this, ['method' => 'paypal', 'card_token' => 'tkn_x']);

    $order = Order::query()->sole();

    $response->assertRedirect(route('shop.checkout.show', ['locale' => 'de']))
        ->assertSessionHas('cart.items', [$this->product->id => 1]);

    expect($order->isPlaced())->toBeFalse()
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($this->product->refresh()->stock)->toBe(4)
        ->and(OnlineGateway::$started[0]['method'])->toBe('paypal')
        ->and(OnlineGateway::$started[0]['card_token'])->toBe('tkn_x')
        // The provider returns into the window the checkout opened.
        ->and(OnlineGateway::$started[0]['return'])->toContain('window=1');

    Mail::assertNothingSent();

    // Where the window goes and where the checkout asks how it went.
    $response->assertInertiaFlash('payment.url', 'https://pay.example/tr_'.$order->id.'_1');

    // A draft is nobody's order yet.
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.index'))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
});

test('once the payment is confirmed the order is placed, confirmed by mail and the cart emptied', function () {
    payOnline($this);
    $order = Order::query()->sole();

    $this->getJson(route('shop.payments.status', ['locale' => 'de', 'order' => $order]))->assertJson(['state' => 'open']);

    OnlineGateway::$outcome = PaymentStatus::Paid;

    $this->getJson(route('shop.payments.status', ['locale' => 'de', 'order' => $order]))
        ->assertJson(['state' => 'done', 'url' => route('shop.orders.complete', ['locale' => 'de', 'order' => $order])])
        ->assertSessionMissing('cart.items');

    expect($order->refresh()->isPlaced())->toBeTrue()->and($order->status)->toBe(OrderStatus::Paid);

    Mail::assertSent(OrderConfirmation::class, 1);
    Mail::assertNotSent(OrderReceived::class);
});

test('a payment that fails ends the attempt: stock back, cart kept, and a second try is a new attempt', function () {
    payOnline($this);
    $first = Order::query()->sole();

    OnlineGateway::$outcome = PaymentStatus::Failed;

    $this->getJson(route('shop.payments.status', ['locale' => 'de', 'order' => $first]))
        ->assertJson(['state' => 'failed'])
        ->assertSessionHas('cart.items', [$this->product->id => 1]);

    expect($first->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($first->isPlaced())->toBeFalse()
        ->and($this->product->refresh()->stock)->toBe(5);

    OnlineGateway::$outcome = PaymentStatus::Paid;

    // Paid at once (a card its bank does not ask about): straight to the thank-you page.
    $second = payOnline($this);

    $placed = Order::query()->placed()->sole();
    $second->assertRedirect(route('shop.orders.complete', ['locale' => 'de', 'order' => $placed]));
    expect($this->product->refresh()->stock)->toBe(4);
    Mail::assertSent(OrderConfirmation::class, 1);
});

test('a payment refused at once returns to the checkout with a message', function () {
    OnlineGateway::$outcome = PaymentStatus::Failed;

    payOnline($this)->assertRedirect(route('shop.checkout.show', ['locale' => 'de']))->assertSessionHasErrors('gateway');

    expect(Order::query()->sole()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->product->refresh()->stock)->toBe(5);
});

test('the customer can give up an attempt, but not one that has been paid', function () {
    payOnline($this);
    $order = Order::query()->sole();

    OnlineGateway::$outcome = PaymentStatus::Paid;

    // The money arrived while they clicked cancel: the order stands.
    $this->deleteJson(route('shop.payments.cancel', ['locale' => 'de', 'order' => $order]))->assertJson(['state' => 'done']);
    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});

test('the window the provider returns into tells the checkout and closes; a stranger cannot ask about an order', function () {
    payOnline($this);
    $order = Order::query()->sole();

    $this->get(route('shop.payments.return', ['locale' => 'de', 'order' => $order, 'window' => 1]))
        ->assertOk()
        ->assertSee('raumladen-payment');

    $this->flushSession();
    $this->getJson(route('shop.payments.status', ['locale' => 'de', 'order' => $order]))->assertForbidden();
});

test('abandoned attempts are given up by the scheduled command, which asks the provider first', function () {
    payOnline($this);
    $abandoned = Order::query()->sole();
    $abandoned->forceFill(['created_at' => now()->subHours(3)])->save();

    $this->artisan('orders:cancel-unpaid')->assertSuccessful();

    expect($abandoned->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->product->refresh()->stock)->toBe(5);
});

test('an Apple Pay token confirmed at the checkout is handed to the provider', function () {
    payOnline($this, ['method' => 'applepay', 'apple_pay_token' => '{"paymentData":{"version":"EC_v1"}}']);

    expect(OnlineGateway::$started[0]['method'])->toBe('applepay')
        ->and(OnlineGateway::$started[0]['apple_pay_token'])->toBe('{"paymentData":{"version":"EC_v1"}}');
});

test('the shop vouches for itself to Apple only, and only when Mollie is set up', function (string $url, int $status) {
    $this->postJson(route('shop.checkout.apple-pay-session', ['locale' => 'de']), ['validation_url' => $url])->assertStatus($status);
})->with([
    'another host' => ['https://evil.example/paymentservices/startSession', 422],
    'a look-alike host' => ['https://apple.com.evil.example/startSession', 422],
    'not https' => ['http://apple-pay-gateway.apple.com/paymentservices/startSession', 422],
    'Apple, without a Mollie key' => ['https://apple-pay-gateway.apple.com/paymentservices/startSession', 404],
]);
