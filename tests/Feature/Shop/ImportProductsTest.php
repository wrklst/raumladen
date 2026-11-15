<?php

use App\Enums\TaxClass;
use App\Models\Product;

function catalogueFile(array $entries): string
{
    $file = tempnam(sys_get_temp_dir(), 'catalogue');
    file_put_contents($file, json_encode($entries));

    return $file;
}

test('a catalogue file creates products and a second run updates them without touching what it does not carry', function () {
    $entry = [
        'sku' => 'SI-1',
        'title' => ['de' => 'Über Räume', 'en' => 'On Rooms'],
        'price' => 10,
        'specs' => ['isbn' => '978-3-00-000001-1', 'authors' => 'A. Author'],
    ];

    $this->artisan('shop:import-products', ['file' => catalogueFile([$entry])])
        ->expectsOutputToContain('Created 1 and updated 0')
        ->assertSuccessful();

    $product = Product::query()->sole();

    expect($product->price_cents)->toBe(1000)
        ->and($product->slug)->toBe(['de' => 'uber-raume', 'en' => 'on-rooms'])
        ->and($product->tax_class)->toBe(TaxClass::Reduced)
        ->and($product->is_active)->toBeTrue()
        ->and($product->specs['authors'])->toBe('A. Author');

    $product->update(['stock' => 7]);

    $this->artisan('shop:import-products', ['file' => catalogueFile([[...$entry, 'price' => 12.5]])])
        ->expectsOutputToContain('Created 0 and updated 1')
        ->assertSuccessful();

    expect($product->refresh()->price_cents)->toBe(1250)
        ->and($product->stock)->toBe(7);
});

test('one invalid entry stops the whole import', function () {
    $this->artisan('shop:import-products', ['file' => catalogueFile([
        ['sku' => 'SI-1', 'title' => ['de' => 'Gut', 'en' => 'Good'], 'price' => 10],
        ['sku' => 'SI-2', 'title' => ['de' => 'Ohne Preis', 'en' => 'No price']],
    ])])->assertFailed();

    expect(Product::query()->count())->toBe(0);
});

test('a file that is missing or not JSON is reported', function () {
    $this->artisan('shop:import-products', ['file' => '/nowhere/catalogue.json'])->assertFailed();
});
