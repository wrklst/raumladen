<?php

use App\Mail\OrderReceived;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    config(['shop.seller.iban' => 'DE02120300000000202051', 'shop.seller.bic' => 'BYLADEM1001']);
    Storage::fake('local');
});

function orderPayload(array $extra = []): array
{
    return [
        'email' => 'kundin@example.com',
        'billing_address' => ['first_name' => 'Erika', 'last_name' => 'Mustermann', 'address1' => 'Weg 1', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE'],
        'gateway' => 'bank_transfer',
        ...$extra,
    ];
}

test('placing an order sends the confirmation of receipt at once, before any payment', function () {
    Mail::fake();
    $product = Product::factory()->create(['price_cents' => 2000]);

    $this->withSession(['cart.items' => [$product->id => 1]])
        ->post(route('shop.checkout.store', ['locale' => 'de']), orderPayload())
        ->assertRedirect();

    $order = Order::query()->sole();

    expect($order->isPaid())->toBeFalse();
    Mail::assertSent(OrderReceived::class, fn (OrderReceived $mail) => $mail->hasTo('kundin@example.com')
        && $mail->order->is($order)
        && $mail->payByBankTransfer);
});

test('the confirmation carries the order, the bank details, the terms and the withdrawal information', function () {
    Page::factory()->create(['slug' => 'terms', 'title' => ['de' => 'AGB'], 'body' => ['de' => 'Es gelten diese Bedingungen.']]);
    Page::factory()->create(['slug' => 'withdrawal', 'title' => ['de' => 'Widerrufsbelehrung'], 'body' => ['de' => 'Sie haben das Recht, binnen vierzehn Tagen zu widerrufen.']]);
    Page::factory()->create(['slug' => 'privacy', 'title' => ['de' => 'Datenschutz'], 'body' => ['de' => 'Nicht in der Mail.']]);

    $order = Order::factory()->create(['number' => 'B-00007', 'locale' => 'de', 'total_cents' => 2390]);
    $order->items()->create(['name' => 'Testbuch', 'quantity' => 1, 'unit_price_cents' => 2000, 'tax_class' => 'reduced', 'tax_rate' => 7, 'tax_category' => 'S', 'discount_cents' => 0, 'line_net_cents' => 1869, 'line_tax_cents' => 131, 'line_gross_cents' => 2000]);

    app()->setLocale('de');

    (new OrderReceived($order, payByBankTransfer: true))
        ->assertHasSubject('Wir haben Ihre Bestellung B-00007 erhalten')
        ->assertSeeInText('Testbuch')
        ->assertSeeInText('DE02120300000000202051')
        ->assertSeeInText('Es gelten diese Bedingungen.')
        ->assertSeeInText('binnen vierzehn Tagen zu widerrufen')
        ->assertDontSeeInText('Nicht in der Mail.');

    (new OrderReceived($order))->assertDontSeeInText('DE02120300000000202051');
});

test('a mail failure does not lose the order', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));
    $product = Product::factory()->create();

    $this->withSession(['cart.items' => [$product->id => 1]])
        ->post(route('shop.checkout.store', ['locale' => 'de']), orderPayload())
        ->assertRedirect();

    expect(Order::query()->count())->toBe(1);
});

test('a guest order under an existing customer email does not rewrite that customer profile', function () {
    Mail::fake();
    $customer = User::factory()->create([
        'email' => 'kundin@example.com',
        'billing_address' => ['first_name' => 'Erika', 'last_name' => 'Original', 'address1' => 'Alte Straße 5', 'zip' => '50667', 'city' => 'Köln', 'country' => 'DE'],
    ]);
    $product = Product::factory()->create();

    $this->withSession(['cart.items' => [$product->id => 1]])
        ->post(route('shop.checkout.store', ['locale' => 'de']), orderPayload(['billing_address' => ['first_name' => 'Fremd', 'last_name' => 'Person', 'address1' => 'Andere Straße 9', 'zip' => '80331', 'city' => 'München', 'country' => 'DE']]))
        ->assertRedirect();

    // The order is theirs to see after signing in; their stored address is untouched.
    expect(Order::query()->sole()->user_id)->toBe($customer->id)
        ->and($customer->refresh()->billing_address['address1'])->toBe('Alte Straße 5');
});

test('a signed-in customer keeps their profile in step with their latest order', function () {
    Mail::fake();
    $customer = User::factory()->create(['email' => 'kundin@example.com', 'billing_address' => ['address1' => 'Alte Straße 5', 'country' => 'DE']]);
    $product = Product::factory()->create();

    $this->actingAs($customer)->withSession(['cart.items' => [$product->id => 1]])
        ->post(route('shop.checkout.store', ['locale' => 'de']), orderPayload())
        ->assertRedirect();

    expect($customer->refresh()->billing_address['address1'])->toBe('Weg 1');
});
