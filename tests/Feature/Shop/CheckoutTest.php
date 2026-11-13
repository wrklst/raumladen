<?php

use App\Enums\OrderStatus;
use App\Enums\TaxTreatment;
use App\Mail\OrderConfirmation;
use App\Models\CustomerGroup;
use App\Models\DatevExport;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Tax\TaxResolver;
use App\Tax\VatIdValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    config(['shop.seller.iban' => 'DE02120300000000202051']);
    Storage::fake('local');
    Mail::fake();
});

function checkoutPayload(string $country = 'DE', array $extra = []): array
{
    return [
        'email' => 'kunde@example.com',
        'billing_address' => [
            'first_name' => 'Erika', 'last_name' => 'Mustermann', 'address1' => 'Weg 1',
            'zip' => '10115', 'city' => 'Berlin', 'country' => $country,
        ],
        'gateway' => 'bank_transfer',
        'terms' => '1',
        ...$extra,
    ];
}

test('a domestic order is placed, paid, invoiced and exported to DATEV', function () {
    $product = Product::factory()->create(['price_cents' => 2000, 'stock' => 5]);

    $this->withSession(['cart.items' => [$product->id => 1]])
        ->post(route('shop.checkout.store', ['locale' => 'de']), checkoutPayload())
        ->assertRedirect();

    $order = Order::query()->sole();

    expect($order->tax_treatment)->toBe(TaxTreatment::Domestic)
        ->and($order->total_cents)->toBe(2390)
        ->and($order->tax_cents)->toBe(157)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($product->refresh()->stock)->toBe(4);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.orders.confirm-payment', $order))
        ->assertRedirect();

    $invoice = $order->refresh()->invoices()->sole();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($invoice->number)->toBe('RE-00001')
        ->and($invoice->total_gross_cents)->toBe(2390);
    Storage::disk('local')->assertExists($invoice->pdf_path);
    Mail::assertSent(OrderConfirmation::class);

    $this->actingAs($admin)
        ->post(route('admin.datev.store'), ['from' => now()->toDateString(), 'to' => now()->toDateString()])
        ->assertRedirect(route('admin.datev.index'));

    $export = DatevExport::query()->sole();
    $csv = Storage::disk('local')->get($export->path);

    expect($export->booking_count)->toBe(1)
        ->and($csv)->toContain('23,90;"S";"EUR"')
        ->and($csv)->toContain(';10000;4300;')
        ->and($invoice->refresh()->datev_export_id)->toBe($export->id);
});

test('an EU business with a confirmed VAT ID is not charged VAT', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => true])]);
    $product = Product::factory()->create(['price_cents' => 2000]);

    $this->withSession(['cart.items' => [$product->id => 1]])
        ->post(route('shop.checkout.store', ['locale' => 'de']), checkoutPayload('AT', ['vat_id' => 'ATU12345678']))
        ->assertRedirect();

    $order = Order::query()->sole();

    expect($order->tax_treatment)->toBe(TaxTreatment::IntraCommunity)
        ->and($order->tax_cents)->toBe(0)
        ->and($order->total_cents)->toBe(1869 + 925)
        ->and($order->tax_note)->not->toBeNull();
});

test('a rejected VAT ID blocks the order instead of silently charging VAT', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => false])]);
    $product = Product::factory()->create();

    $this->withSession(['cart.items' => [$product->id => 1]])
        ->post(route('shop.checkout.store', ['locale' => 'de']), checkoutPayload('AT', ['vat_id' => 'ATU00000000']))
        ->assertSessionHasErrors('vat_id');

    expect(Order::query()->count())->toBe(0);
});

test('the tax resolver picks the treatment', function (string $country, bool $vatIdValid, bool $oss, TaxTreatment $expected) {
    config(['shop.tax.oss_registered' => $oss]);

    expect(app(TaxResolver::class)->resolve($country, $vatIdValid))->toBe($expected);
})->with([
    'domestic' => ['DE', false, false, TaxTreatment::Domestic],
    'domestic with vat id' => ['DE', true, false, TaxTreatment::Domestic],
    'eu consumer below threshold' => ['AT', false, false, TaxTreatment::DistanceHome],
    'eu consumer with oss' => ['AT', false, true, TaxTreatment::Oss],
    'eu business' => ['AT', true, true, TaxTreatment::IntraCommunity],
    'third country' => ['CH', false, false, TaxTreatment::Export],
]);
