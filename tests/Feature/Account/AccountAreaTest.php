<?php

use App\Invoicing\InvoiceIssuer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Tax\VatIdValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->consumer = CustomerGroup::factory()->consumer()->create();
    Storage::fake('local');
    $this->customer = User::factory()->create(['customer_group_id' => $this->consumer->id]);
});

function accountUrl(string $name, array $parameters = [], string $locale = 'de'): string
{
    return route($name, ['locale' => $locale, ...$parameters]);
}

function invoicedOrderOf(User $user): Invoice
{
    $order = Order::factory()->paid()->create(['user_id' => $user->id]);
    $order->items()->create([
        'name' => 'Testbuch', 'sku' => 'BK-1', 'quantity' => 1, 'unit_price_cents' => 1070, 'tax_class' => 'reduced',
        'tax_rate' => 7, 'tax_category' => 'S', 'discount_cents' => 0, 'line_net_cents' => 1000, 'line_tax_cents' => 70, 'line_gross_cents' => 1070,
    ]);

    return app(InvoiceIssuer::class)->issueInvoice($order);
}

// Orders

test('the account lists only the signed-in customers own orders', function () {
    $own = Order::factory()->create(['user_id' => $this->customer->id]);
    Order::factory()->create();

    $this->actingAs($this->customer)
        ->get(accountUrl('shop.account.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/orders')
            ->has('orders.data', 1)
            ->where('orders.data.0.uuid', $own->uuid));
});

test('guests are sent to the storefront login in the locale of their session', function (string $locale) {
    $this->withSession(['locale' => $locale])
        ->get(accountUrl('shop.account.orders.index', [], $locale))
        ->assertRedirect(accountUrl('shop.account.login', [], $locale));
})->with(['de', 'en']);

test('a guest opening an English account URL as their first request lands on the English login', function () {
    $this->get(accountUrl('shop.account.orders.index', [], 'en'))->assertRedirect(accountUrl('shop.account.login', [], 'en'));
});

test('a customer opens their own order', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id]);

    $this->actingAs($this->customer)
        ->get(accountUrl('shop.account.orders.show', ['order' => $order]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('account/order')->where('order.uuid', $order->uuid));
});

test('another customers order is forbidden', function () {
    $order = Order::factory()->create();

    $this->actingAs($this->customer)->get(accountUrl('shop.account.orders.show', ['order' => $order]))->assertForbidden();
});

test('an admin may open any order in the account area', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(accountUrl('shop.account.orders.show', ['order' => $order]))
        ->assertOk();
});

test('the customer downloads the PDF of their own invoice', function () {
    $invoice = invoicedOrderOf($this->customer);

    $response = $this->actingAs($this->customer)
        ->get(accountUrl('shop.account.orders.invoice', ['order' => $invoice->order, 'invoice' => $invoice]))
        ->assertOk()
        ->assertDownload('RE-00001.pdf');

    expect($response->streamedContent())->toStartWith('%PDF');
});

test('another customers invoice cannot be downloaded', function () {
    $invoice = invoicedOrderOf(User::factory()->create());

    $this->actingAs($this->customer)
        ->get(accountUrl('shop.account.orders.invoice', ['order' => $invoice->order, 'invoice' => $invoice]))
        ->assertForbidden();
});

test('an invoice of a different order cannot be fetched through my own order', function () {
    $own = Order::factory()->create(['user_id' => $this->customer->id]);
    $foreign = invoicedOrderOf(User::factory()->create());

    $this->actingAs($this->customer)
        ->get(accountUrl('shop.account.orders.invoice', ['order' => $own, 'invoice' => $foreign]))
        ->assertNotFound();
});

test('an invoice without a stored PDF is a 404', function () {
    $order = Order::factory()->paid()->create(['user_id' => $this->customer->id]);
    $invoice = Invoice::query()->create([
        'type' => 'invoice', 'number' => 'RE-00099', 'order_id' => $order->id, 'issued_at' => now(), 'currency' => 'EUR',
        'total_net_cents' => 1, 'total_tax_cents' => 0, 'total_gross_cents' => 1, 'snapshot' => [],
    ]);

    $this->actingAs($this->customer)
        ->get(accountUrl('shop.account.orders.invoice', ['order' => $order, 'invoice' => $invoice]))
        ->assertNotFound();
});

// Profile

function profilePayload(array $overrides = []): array
{
    return [
        'name' => 'Erika Mustermann',
        'company' => 'Buch GmbH',
        'phone' => '030 123456',
        'locale' => 'de',
        'billing_address' => ['first_name' => 'Erika', 'last_name' => 'Mustermann', 'address1' => 'Weg 1', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE'],
        ...$overrides,
    ];
}

test('the profile page shows the stored data', function () {
    $this->customer->update(['company' => 'Buch GmbH', 'vat_id' => 'ATU12345678', 'vat_id_validated_at' => now()]);

    $this->actingAs($this->customer)
        ->get(accountUrl('shop.account.profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/profile')
            ->where('profile.email', $this->customer->email)
            ->where('profile.company', 'Buch GmbH')
            ->where('profile.vat_id_valid', true)
            ->where('profile.customer_group', 'Consumers'));
});

test('the profile is closed to guests', function () {
    $this->get(accountUrl('shop.account.profile.edit'))->assertRedirect(accountUrl('shop.account.login'));
    $this->patch(accountUrl('shop.account.profile.update'), profilePayload())->assertRedirect(accountUrl('shop.account.login'));
});

test('a customer updates their profile and addresses', function () {
    $this->actingAs($this->customer)
        ->patch(accountUrl('shop.account.profile.update'), profilePayload(['shipping_address' => ['city' => 'Hamburg', 'country' => 'DE']]))
        ->assertSessionHasNoErrors();

    $this->customer->refresh();

    expect($this->customer->name)->toBe('Erika Mustermann')
        ->and($this->customer->company)->toBe('Buch GmbH')
        ->and($this->customer->phone)->toBe('030 123456')
        ->and($this->customer->billing_address['city'])->toBe('Berlin')
        ->and($this->customer->shipping_address['city'])->toBe('Hamburg')
        ->and($this->customer->vat_id)->toBeNull()
        ->and($this->customer->vat_id_validated_at)->toBeNull();
});

test('the profile update is validated', function (array $overrides, string $field) {
    $this->actingAs($this->customer)
        ->patch(accountUrl('shop.account.profile.update'), profilePayload($overrides))
        ->assertSessionHasErrors($field);

    expect($this->customer->refresh()->name)->not->toBe('Changed');
})->with([
    'name missing' => [['name' => ''], 'name'],
    'unsupported locale' => [['locale' => 'fr'], 'locale'],
    'country not two letters' => [['billing_address' => ['country' => 'DEU']], 'billing_address.country'],
    'zip too long' => [['billing_address' => ['zip' => str_repeat('1', 21)]], 'billing_address.zip'],
    'vat id too long' => [['vat_id' => str_repeat('A', 21)], 'vat_id'],
]);

test('a confirmed VAT ID is normalized and stamped', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => true])]);

    $this->actingAs($this->customer)
        ->patch(accountUrl('shop.account.profile.update'), profilePayload(['vat_id' => 'at u12345678']))
        ->assertSessionHasNoErrors();

    $this->customer->refresh();

    expect($this->customer->vat_id)->toBe('ATU12345678')
        ->and($this->customer->vat_id_validated_at)->not->toBeNull()
        ->and($this->customer->hasValidVatId())->toBeTrue();
});

test('a VAT ID that VIES rejects is refused and nothing is saved', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => false])]);

    $this->actingAs($this->customer)
        ->patch(accountUrl('shop.account.profile.update'), profilePayload(['name' => 'Changed', 'vat_id' => 'ATU00000000']))
        ->assertSessionHasErrors('vat_id');

    $this->customer->refresh();

    expect($this->customer->vat_id)->toBeNull()->and($this->customer->name)->not->toBe('Changed');
});

test('an unavailable VIES saves the VAT ID without stamping it', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response('', 503)]);

    $this->actingAs($this->customer)
        ->patch(accountUrl('shop.account.profile.update'), profilePayload(['vat_id' => 'ATU12345678']))
        ->assertSessionHasNoErrors();

    $this->customer->refresh();

    expect($this->customer->vat_id)->toBe('ATU12345678')
        ->and($this->customer->vat_id_validated_at)->toBeNull()
        ->and($this->customer->hasValidVatId())->toBeFalse();
});

test('removing the VAT ID removes its validation', function () {
    $this->customer->update(['vat_id' => 'ATU12345678', 'vat_id_validated_at' => now()]);

    $this->actingAs($this->customer)
        ->patch(accountUrl('shop.account.profile.update'), profilePayload(['vat_id' => '']))
        ->assertSessionHasNoErrors();

    $this->customer->refresh();

    expect($this->customer->vat_id)->toBeNull()->and($this->customer->vat_id_validated_at)->toBeNull();
});

test('a customer cannot change their role or group through the profile', function () {
    $business = CustomerGroup::factory()->business()->create();

    $this->actingAs($this->customer)
        ->patch(accountUrl('shop.account.profile.update'), profilePayload(['role' => 'admin', 'customer_group_id' => $business->id, 'email' => 'new@example.com']))
        ->assertSessionHasNoErrors();

    $this->customer->refresh();

    expect($this->customer->isAdmin())->toBeFalse()
        ->and($this->customer->customer_group_id)->toBe($this->consumer->id)
        ->and($this->customer->email)->not->toBe('new@example.com');
});
