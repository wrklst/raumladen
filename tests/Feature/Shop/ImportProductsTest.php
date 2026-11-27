<?php

use App\Enums\TaxClass;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

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

test('a catalogue entry links its authors and brings the text about them', function () {
    $this->artisan('shop:import-products', ['file' => catalogueFile([[
        'sku' => 'SI-1',
        'title' => ['en' => 'On Rooms'],
        'price' => 10,
        'authors' => [
            ['name' => 'A. Author', 'bio' => ['en' => 'Writes about rooms.']],
            ['name' => 'B. Author'],
        ],
    ]])])->assertSuccessful();

    $product = Product::query()->sole();

    expect($product->specs['authors'])->toBe('A. Author, B. Author')
        ->and($product->authors->pluck('bio.en')->all())->toBe(['Writes about rooms.', null]);
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

test('a catalogue entry brings the picture of its back cover along', function () {
    Storage::fake('public');
    $file = catalogueFile([]);
    imagepng(imagecreatetruecolor(4, 6), dirname($file).'/back.png');
    $entry = ['sku' => 'SI-1', 'title' => ['de' => 'Raum', 'en' => 'Space'], 'price' => 10];

    file_put_contents($file, json_encode([[...$entry, 'back_cover' => 'back.png']]));
    $this->artisan('shop:import-products', ['file' => $file])->assertSuccessful();

    $path = Product::query()->sole()->back_cover;
    Storage::disk('public')->assertExists($path);

    file_put_contents($file, json_encode([[...$entry, 'back_cover' => 'missing.png']]));
    $this->artisan('shop:import-products', ['file' => $file])->assertFailed();

    file_put_contents($file, json_encode([[...$entry, 'back_cover' => null]]));
    $this->artisan('shop:import-products', ['file' => $file])->assertSuccessful();

    Storage::disk('public')->assertMissing($path);
    expect(Product::query()->sole()->back_cover)->toBeNull();
});

test('a catalogue entry brings the pictures of the book along, the cover first', function () {
    Storage::fake('public');
    $file = catalogueFile([]);
    imagepng(imagecreatetruecolor(4, 6), dirname($file).'/front.png');
    imagepng(imagecreatetruecolor(6, 4), dirname($file).'/page.png');
    $entry = ['sku' => 'SI-1', 'title' => ['de' => 'Raum', 'en' => 'Space'], 'price' => 10];

    file_put_contents($file, json_encode([[...$entry, 'images' => ['front.png', 'page.png']]]));
    $this->artisan('shop:import-products', ['file' => $file])->assertSuccessful();

    [$front, $page] = Product::query()->sole()->images;
    Storage::disk('public')->assertExists([$front, $page, str_replace('.webp', '-600.webp', $page)]);
    expect(getimagesizefromstring(Storage::disk('public')->get($front))['mime'])->toBe('image/webp');

    file_put_contents($file, json_encode([[...$entry, 'images' => ['missing.png']]]));
    $this->artisan('shop:import-products', ['file' => $file])->assertFailed();

    file_put_contents($file, json_encode([[...$entry, 'images' => ['front.png']]]));
    $this->artisan('shop:import-products', ['file' => $file])->assertSuccessful();

    Storage::disk('public')->assertExists($front)->assertMissing([$page, str_replace('.webp', '-600.webp', $page)]);
    expect(Product::query()->sole()->images)->toBe([$front]);
});
